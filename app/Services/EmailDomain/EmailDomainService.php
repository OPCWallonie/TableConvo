<?php

namespace App\Services\EmailDomain;

use App\Models\Company;

class EmailDomainService
{
    private const GENERIC_PROVIDERS = [
        // Webmail internationaux
        'gmail.com',
        'googlemail.com',
        'hotmail.com',
        'hotmail.be',
        'hotmail.fr',
        'outlook.com',
        'outlook.be',
        'outlook.fr',
        'live.com',
        'live.be',
        'live.fr',
        'yahoo.com',
        'yahoo.fr',
        'yahoo.be',
        'proton.me',
        'protonmail.com',
        'protonmail.ch',
        'icloud.com',
        'me.com',
        'mac.com',
        'aol.com',
        'msn.com',
        // FAI belges
        'skynet.be',
        'telenet.be',
        'voo.be',
        'scarlet.be',
        'belgacom.net',
        'proximus.be',
        'swing.be',
        'brutele.be',
        // FAI français
        'orange.fr',
        'wanadoo.fr',
        'free.fr',
        'sfr.fr',
        'laposte.net',
        'bbox.fr',
        'numericable.fr',
        // Autres courants
        'gmx.com',
        'gmx.fr',
        'gmx.be',
        'mail.com',
        'ymail.com',
        'inbox.com',
    ];

    public function extract(string $email): ?string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            return null;
        }

        $domain = strtolower($parts[1]);

        // Validation basique du domaine
        if (! filter_var('user@' . $domain, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $domain;
    }

    public function isGenericProvider(string $domain): bool
    {
        return in_array(strtolower($domain), self::GENERIC_PROVIDERS, true);
    }

    public function isAcceptableCompanyDomain(string $email): bool
    {
        $domain = $this->extract($email);

        if ($domain === null) {
            return false;
        }

        return ! $this->isGenericProvider($domain);
    }

    public function findCompanyByDomain(string $domain): ?Company
    {
        return Company::where('email_domain', strtolower($domain))->first();
    }
}
