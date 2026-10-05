<?php

namespace App\Support;

/**
 * Numéro de mobile sénégalais : 9 chiffres, préfixe opérateur 70, 71, 75, 76, 77 ou 78.
 * Stocké sans indicatif (771234567), affiché « 77 123 45 67 », joint sur WhatsApp via 221771234567@c.us.
 */
final class SenegalPhone
{
    public const PREFIXES = ['70', '71', '75', '76', '77', '78'];

    public const PATTERN = '/^(70|71|75|76|77|78)[0-9]{7}$/';

    /**
     * Retire espaces, points, tirets et l'indicatif +221 / 00221 / 221.
     * Renvoie la saisie nettoyée, valide ou non (la validation se fait à part).
     */
    public static function clean(?string $input): string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        foreach (['00221', '221'] as $prefix) {
            if (strlen($digits) === 9 + strlen($prefix) && str_starts_with($digits, $prefix)) {
                return substr($digits, strlen($prefix));
            }
        }

        return $digits;
    }

    public static function isValid(?string $phone): bool
    {
        return (bool) preg_match(self::PATTERN, (string) $phone);
    }

    /** Numéro normalisé (9 chiffres) ou null s'il n'est pas un mobile sénégalais valide. */
    public static function normalize(?string $input): ?string
    {
        $phone = self::clean($input);

        return self::isValid($phone) ? $phone : null;
    }

    /** « 77 123 45 67 » */
    public static function format(?string $phone): ?string
    {
        if (!self::isValid($phone)) {
            return $phone;
        }

        return substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5, 2) . ' ' . substr($phone, 7, 2);
    }

    /** « 77 *** ** 67 » : affiché pendant la saisie du code OTP. */
    public static function mask(?string $phone): ?string
    {
        if (!self::isValid($phone)) {
            return null;
        }

        return substr($phone, 0, 2) . ' *** ** ' . substr($phone, 7, 2);
    }

    /** Identifiant de discussion WAHA. */
    public static function chatId(?string $phone): ?string
    {
        $phone = self::normalize($phone);

        return $phone ? '221' . $phone . '@c.us' : null;
    }
}
