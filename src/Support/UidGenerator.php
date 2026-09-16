<?php

declare(strict_types=1);

namespace WPRC\Core\Support;

use RuntimeException;

defined('ABSPATH') || exit;

final class UidGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const DEFAULT_LENGTH = 8;

    public function generate(int $length = self::DEFAULT_LENGTH): string
    {
        if ($length < 4 || $length > 64) {
            throw new RuntimeException('RC UID length must be between 4 and 64 characters.');
        }

        $uid = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $uid .= self::ALPHABET[random_int(0, $max)];
        }

        return $uid;
    }

    /**
     * Generate a UID that does not already exist according to the supplied callback.
     *
     * @param callable(string):bool $exists Returns true when the UID already exists.
     */
    public function generateUnique(callable $exists, int $length = self::DEFAULT_LENGTH, int $maxAttempts = 32): string
    {
        for ($attempt = 0; $attempt < max(1, $maxAttempts); $attempt++) {
            $uid = $this->generate($length);
            if (!$exists($uid)) {
                return $uid;
            }
        }

        throw new RuntimeException('Unable to generate a unique RC UID after the configured number of attempts.');
    }
}
