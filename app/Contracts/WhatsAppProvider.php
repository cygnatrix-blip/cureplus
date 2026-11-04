<?php

namespace App\Contracts;

interface WhatsAppProvider
{
    /**
     * @param  list<string>  $parameters
     * @return string The provider's message id
     */
    public function sendTemplate(string $to, string $template, array $parameters): string;
}
