<?php

declare(strict_types=1);

namespace Pest\Arch;

use Pest\Arch\Collections\Dependencies;
use Pest\Arch\Options\LayerOptions;
use Pest\Arch\ValueObjects\Targets;
use Pest\Expectation;
use PHPUnit\Architecture\Elements\ObjectDescription;

/**
 * @internal
 *
 * @mixin Expectation<array|string>
 */
final readonly class UnlessModifier
{
    public static function make(Expectation $expectation): self
    {
        return new self($expectation);
    }

    public function __construct(protected Expectation $expectation)
    {
    }

    /* @phpstan-ignore-next-line */
    public function targetsToIgnore(array $expectations, LayerOptions $options): array
    {
        $ignoreArr = [];
        $blueprint = Blueprint::make(
            Targets::fromExpectation($this->expectation),
            Dependencies::fromExpectationInput([]),
        );

        $targets = (fn (): array => $this->target->value)->call($blueprint);
        $layerFactory = (fn (): \Pest\Arch\Factories\LayerFactory => $this->layerFactory)->call($blueprint);

        foreach ($targets as $targetValue) {
            $targetLayer = $layerFactory->make($options, $targetValue);

            foreach ($targetLayer as $object) {
                /** @var \Pest\Arch\Objects\ObjectDescription $objectDescription */
                $objectDescription = $object;

                foreach ($expectations as $expectation => $value) {
                    if ($ignore = $this->handleExpectation($objectDescription, $expectation, $value)) { // @phpstan-ignore-line
                        $ignoreArr[] = $ignore;
                    }
                }
            }
        }

        return $ignoreArr;
    }

    /**
     * Handles the expectation.
     */
    private function handleExpectation(ObjectDescription $objectDescription, string $expectation, mixed $value): ?string
    {
        return match ($expectation) {
            'abstractParent' => $this->handleAbstractParent($objectDescription, $value),
            'extends' => $this->handleExtends($objectDescription, $value),
            default => null,
        };
    }

    /**
     * Handles the "extends" expectation.
     */
    private function handleExtends(ObjectDescription $objectDescription, mixed $value): ?string
    {
        $reflection = $objectDescription->reflectionClass;

        if ($value === true && $reflection->getParentClass() !== false) {
            return $reflection->getName();
        }

        if (! is_string($value)) {
            return null;
        }

        if (! $reflection->isSubclassOf($value)) {
            return null;
        }

        return $reflection->getName();
    }

    /**
     * Handles the "abstractParent" expectation.
     */
    private function handleAbstractParent(ObjectDescription $objectDescription, mixed $value): ?string
    {
        $reflection = $objectDescription->reflectionClass;

        if ($reflection->getParentClass() === false) {
            return null;
        }

        if ($value !== true) {
            return null;
        }

        if (! $reflection->getParentClass()->isAbstract()) {
            return null;
        }

        return $reflection->getName();
    }
}
