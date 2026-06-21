<?php

declare(strict_types=1);

namespace Maiscraft\GraphQLHyperf\Contract;

use Maiscraft\GraphQL\Contract\ValidatorInterface;
use Hyperf\Validation\ValidatorFactory;
use Hyperf\Validation\Validator;

class HyperfValidator implements ValidatorInterface
{
    private ValidatorFactory $validatorFactory;
    private ?Validator $lastValidator = null;

    public function __construct(ValidatorFactory $validatorFactory)
    {
        $this->validatorFactory = $validatorFactory;
    }

    public function validate(mixed $data, array $rules, array $messages = []): bool
    {
        $this->lastValidator = $this->validatorFactory->make($data, $rules, $messages);
        return $this->lastValidator->passes();
    }

    public function errors(): array
    {
        if ($this->lastValidator === null) {
            return [];
        }

        $errors = [];
        foreach ($this->lastValidator->errors()->all() as $error) {
            $errors[] = $error;
        }
        return $errors;
    }

    public function validateField(string $field, mixed $value, string $rule): bool
    {
        $this->lastValidator = $this->validatorFactory->make(
            [$field => $value],
            [$field => $rule]
        );
        return $this->lastValidator->passes();
    }
}
