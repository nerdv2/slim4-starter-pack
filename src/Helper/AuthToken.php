<?php

declare(strict_types=1);

namespace App\Helper;

use DateTimeImmutable;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use Lcobucci\Clock\FrozenClock;

final class AuthToken
{
    public static function generate()
    {
        $tokenBuilder = (new Builder(new JoseEncoder(), ChainedFormatter::default()));
        $algorithm    = new Sha256();
        $signingKey   = InMemory::plainText($_SERVER['APP_KEY']);

        $now   = new DateTimeImmutable();
        $token = $tokenBuilder
            ->issuedBy($_SERVER['APP_BASE_URL'])
            ->permittedFor($_SERVER['APP_BASE_URL'])
            ->identifiedBy('4f1g23a12aa')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now->modify('+1 second'))
            ->expiresAt($now->modify('+12 hour'))
            ->withClaim('uid', 1)
            ->getToken($algorithm, $signingKey);

        return $token->toString();
    }

    public static function validate($token)
    {
        $status = true;

        $parser = new Parser(new JoseEncoder());
        $token = $parser->parse($token);

        $validator = new Validator();

        try {
            $signingKey   = InMemory::plainText($_SERVER['APP_KEY']);
            $validator->assert($token, new IdentifiedBy('4f1g23a12aa'));
            $validator->assert($token, new IssuedBy($_SERVER['APP_BASE_URL']));
            $validator->assert($token, new PermittedFor($_SERVER['APP_BASE_URL']));
            $validator->assert($token, new SignedWith(new Sha256(), $signingKey));
            $validator->assert($token, new StrictValidAt(
                new FrozenClock(new \DateTimeImmutable())
            ));
        } catch (RequiredConstraintsViolated $e) {
            $status = false;
        }

        return $status;
    }
}