<?php

namespace App\Services\Agt;

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Wrapper fino sobre os endpoints REST da AGT.
 * Auth: Basic Auth (confirmado em api.html) — NÃO é header Username/Password.
 */
class AgtClient
{
    private Company $company;

    public function __construct(?Company $company = null)
    {
        $this->company = $company ?? Company::firstOrFail();
    }

    public function baseUrl(): string
    {
        $env = $this->company->agt_env ?? config('agt.env');
        return config("agt.endpoints.{$env}");
    }

    public function solicitarSerie(array $payload): Response
    {
        return $this->post('/solicitarSerie', $payload);
    }

    public function registarFactura(array $payload): Response
    {
        return $this->post('/registarFactura', $payload);
    }

    public function obterEstado(array $payload): Response
    {
        return $this->post('/obterEstado', $payload);
    }

    public function consultarFactura(array $payload): Response
    {
        return $this->post('/consultarFactura', $payload);
    }

    public function listarFacturas(array $payload): Response
    {
        return $this->post('/listarFacturas', $payload);
    }

    public function listarSeries(array $payload): Response
    {
        return $this->post('/listarSeries', $payload);
    }

    public function validarDocumento(array $payload): Response
    {
        return $this->post('/validarDocumento', $payload);
    }

    private function post(string $path, array $payload): Response
    {
        $username = $this->company->agt_username ?? config('agt.username');
        $password = $this->company->agt_password_encrypted
            ? Crypt::decryptString($this->company->agt_password_encrypted)
            : config('agt.password');

        if (!$username || !$password) {
            throw new RuntimeException(
                'Credenciais AGT não configuradas. Preencha AGT_USERNAME/AGT_PASSWORD no .env '
                . 'ou agt_username/agt_password_encrypted na tabela company.'
            );
        }

        return Http::withBasicAuth($username, $password)
            ->acceptJson()
            ->timeout(config('agt.timeout'))
            ->retry(config('agt.retries'), 500)
            ->post($this->baseUrl() . $path, $payload);
    }
}