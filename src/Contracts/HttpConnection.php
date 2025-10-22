<?php

namespace YasserElgammal\LaraSms\Contracts;

interface HttpConnection
{
    public function get(string $url, array $headers = []): array;
    public function post(string $url, array $data = [], array $headers = []): array;
}
