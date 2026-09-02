<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressão: um cliente HTTP que NÃO manda "Accept: application/json"
 * (curl puro, por exemplo — getJson()/postJson() mandam esse header e
 * mascaravam o bug) precisa continuar recebendo 401 limpo, não 500 por
 * tentativa de redirect pra uma rota "login" que não existe nesta API.
 */
class UnauthenticatedResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_without_accept_header_still_returns_401_json(): void
    {
        $response = $this->call('GET', '/api/v1/patients');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
    }
}
