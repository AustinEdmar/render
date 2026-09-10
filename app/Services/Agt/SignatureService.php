<?php

namespace App\Services\Agt;

use Firebase\JWT\JWT;
use RuntimeException;

/**
 * Gera as assinaturas JWS (RS256) exigidas pela API da AGT.
 *
 * IMPORTANTE (confirmado em
 * https://portaldoparceiro.hml.minfin.gov.ao/doc-agt/faturacao-electronica/1/estrutura.html):
 *
 * - NÃO é concatenação de campos. É um JWT compacto: header.payload.signature
 * - O payload é o objecto JSON *inteiro* (canónico: sem espaços, aspas duplas).
 * - Usa-se sempre RS256 + chave privada.
 * - firebase/php-jwt já produz exactamente este formato (JWT::encode).
 *
 * CORREÇÃO (comparado com o SDK Node agt-fe-sdk, confirmado como funcional):
 * signSeriesRequest() assinava 4 campos (incluindo establishmentNumber), mas
 * a implementação que funciona assina só 3 (taxRegistrationNumber, seriesYear,
 * documentType) — establishmentNumber NÃO entra na assinatura. O campo a
 * mais fazia a verificação da AGT falhar (E40).
 */
class SignatureService
{
    /**
     * Assina um array associativo e devolve o JWT compacto (3 partes).
     */
    public function sign(array $payload, string $privateKeyPath): string
    {
        $pem = $this->loadKey($privateKeyPath);

        return JWT::encode($payload, $pem, 'RS256');
    }

    /**
     * jwsSoftwareSignature — assina o objecto softwareInfoDetail completo.
     */
    public function signSoftwareInfo(array $softwareInfoDetail, string $softwarePrivateKeyPath): string
    {
        return $this->sign([
            'productId' => $softwareInfoDetail['productId'],
            'productVersion' => $softwareInfoDetail['productVersion'],
            'softwareValidationNumber' => $softwareInfoDetail['softwareValidationNumber'],
            'signatureVersion' => $softwareInfoDetail['signatureVersion'],
        ], $softwarePrivateKeyPath);
    }

    /**
     * jwsDocumentSignature — assina os campos principais do documento fiscal.
     * Campos exactos conforme documentação: documentNo, taxRegistrationNumber,
     * documentType, documentDate, customerTaxID, customerCountry, companyName,
     * documentTotals (objecto completo).
     */
    public function signDocument(array $document, string $taxRegistrationNumber, string $privateKeyPath): string
    {
        return $this->sign([
            'documentNo' => $document['documentNo'],
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'documentType' => $document['documentType'],
            'documentDate' => $document['documentDate'],
            'customerTaxID' => $document['customerTaxID'],
            'customerCountry' => $document['customerCountry'],
            'companyName' => $document['companyName'],
            'documentTotals' => $document['documentTotals'],
        ], $privateKeyPath);
    }

    /**
     * jwsSignature — assinatura da requisição solicitarSerie.
     *
     * CONFIRMADO na documentação oficial da AGT (estrutura.html / solicitar.html):
     * "Os campos da solicitação a serem utilizados na assinatura são:
     * taxRegistrationNumber, establishmentNumber, seriesYear, documentType"
     */
    public function signSeriesRequest(
        string $taxRegistrationNumber,
        string $establishmentNumber,
        string $seriesYear,
        string $documentType,
        string $privateKeyPath
    ): string {
        return $this->sign([
            'taxRegistrationNumber' => $taxRegistrationNumber,
            'establishmentNumber' => $establishmentNumber,
            'seriesYear' => $seriesYear,
            'documentType' => $documentType,
        ], $privateKeyPath);
    }

    /** @var array<string,string> cache só em memória do processo, nunca persistido */
    private static array $keyCache = [];

    private function loadKey(string $path): string
    {
        if (!isset(self::$keyCache[$path])) {
            if (!is_readable($path)) {
                throw new RuntimeException("Chave privada AGT não encontrada ou ilegível: {$path}");
            }

            self::$keyCache[$path] = file_get_contents($path);
        }

        return self::$keyCache[$path];
    }
}