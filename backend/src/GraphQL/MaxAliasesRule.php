<?php
declare(strict_types=1);

namespace App\GraphQL;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Validator\QueryValidationContext;
use GraphQL\Validator\Rules\ValidationRule;

class MaxAliasesRule extends ValidationRule
{
    private int $max;

    public function __construct(int $max)
    {
        $this->max = $max;
    }

    public function getVisitor(QueryValidationContext $context): array
    {
        $count = 0;
        return [
            NodeKind::FIELD => function (FieldNode $node) use (&$count, $context): void {
                if ($node->alias === null) {
                    return;
                }
                $count++;
                if ($count === $this->max + 1) {
                    $context->reportError(new Error(
                        "Query uses too many aliases (max {$this->max})."
                    ));
                }
            },
        ];
    }
}
