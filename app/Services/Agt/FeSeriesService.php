<?php

namespace App\Services\Agt;

use App\Models\Company;
use App\Models\FeSeries;
use Illuminate\Support\Str;
use RuntimeException;

class FeSeriesService
{
    public function __construct(
        private AgtClient $client,
        private SignatureService $signer,
    ) {
    }

    /**
     * Garante que existe uma série activa com números disponíveis para o
     * tipo de documento indicado. Se não existir, solicita uma nova à AGT.
     * Devolve o próximo documentNo pronto a usar, no formato exigido:
     * "<documentType> <seriesCode>/<numero>"
     *
     * NOTA: o formato exacto de documentNo confirmado nos exemplos da
     * documentação é "FT FT6325S2C/10006" — ou seja "<tipo> <seriesCode>/<seq>".
     */
    public function nextDocumentNo(
        string $documentType,
        string $establishmentNumber = 'SEDE',
        string $contingencyIndicator = 'N'
    ): string {
        $year = (string) now()->year;

        $series = FeSeries::where('document_type', $documentType)
            ->where('series_year', $year)
            ->where('establishment_number', $establishmentNumber)
            ->where('contingency_indicator', $contingencyIndicator)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (!$series || $series->used_count >= $series->authorized_quantity) {
            $series = $this->requestSeries($documentType, $establishmentNumber, $contingencyIndicator, $year);
        }

        $seq = $series->used_count + 1;
        $series->increment('used_count');

        if ($series->used_count >= $series->authorized_quantity) {
            $series->update(['status' => 'exhausted']);
        }

        return sprintf('%s %s/%d', $documentType, $series->series_code, $seq);
    }

    private function requestSeries(
        string $documentType,
        string $establishmentNumber,
        string $contingencyIndicator,
        string $seriesYear
    ): FeSeries {
        $company = Company::firstOrFail();
        $nif = $company->nif ?? config('agt.tax_registration_number');
        $privateKeyPath = $company->agt_private_key_path ?? config('agt.private_key_path');

        // CORRIGIDO: establishmentNumber já não é passado ao signer — a
        // assinatura agora cobre só taxRegistrationNumber, seriesYear e
        // documentType, alinhado com o SDK Node confirmado como funcional.
        // establishmentNumber continua a ir no PAYLOAD enviado (campo
        // 'establishmentNumber' abaixo), só não entra dentro do JWS.
        $jwsSignature = $this->signer->signSeriesRequest(
            $nif,
            $establishmentNumber,
            $seriesYear,
            $documentType,
            $privateKeyPath
        );



        $submissionUUID = (string) Str::uuid();

        $payload = [
            'schemaVersion' => config('agt.schema_version'),
            'submissionUUID' => $submissionUUID,
            'taxRegistrationNumber' => $nif,
            'submissionTimeStamp' => now()->toIso8601String(),
            'softwareInfo' => $this->buildSoftwareInfo($company),
            'seriesYear' => $seriesYear,
            'documentType' => $documentType,
            'establishmentNumber' => $establishmentNumber,
            'jwsSignature' => $jwsSignature,
            'seriesContingencyIndicator' => $contingencyIndicator,
        ];

        $response = $this->client->solicitarSerie($payload);
        $body = $response->json();

        $series = FeSeries::updateOrCreate(
            [
                'document_type' => $documentType,
                'series_year' => $seriesYear,
                'establishment_number' => $establishmentNumber,
                'contingency_indicator' => $contingencyIndicator,
            ],
            [
                'request_submission_uuid' => $submissionUUID,
                'request_payload' => $payload,
                'response_payload' => $body,
            ]
        );

        if (!$response->successful() || empty($body['seriesFEResult'])) {
            $series->update(['status' => 'rejected']);
            throw new RuntimeException(
                'Falha ao solicitar série AGT: ' . json_encode($body['errorList'] ?? $body)
            );
        }

        $result = $body['seriesFEResult'];

        $series->update([
            'series_code' => $result['seriesCode'],
            'authorized_quantity' => (int) $result['authorizedQuantity'],
            'first_document_no' => $result['firstDocumentNo'],
            'last_document_no' => $result['lastDocumentNo'],
            'status' => 'active',
        ]);

        return $series;
    }

    private function buildSoftwareInfo(Company $company): array
    {
        $detail = [
            'productId' => config('agt.software.product_id'),
            'productVersion' => config('agt.software.product_version'),
            'softwareValidationNumber' => config('agt.software.software_validation_number'),
            'signatureVersion' => 1,
        ];

        return [
            'softwareInfoDetail' => $detail,
            'jwsSoftwareSignature' => $this->signer->signSoftwareInfo(
                $detail,
                $company->agt_software_private_key_path
                ?? $company->agt_private_key_path
                ?? config('agt.software_private_key_path')
                ?? config('agt.private_key_path')
            ),
        ];
    }
}