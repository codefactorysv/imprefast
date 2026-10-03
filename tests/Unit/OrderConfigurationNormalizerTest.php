<?php

namespace Tests\Unit;

use App\Services\OrderConfigurationNormalizer;
use PHPUnit\Framework\TestCase;

class OrderConfigurationNormalizerTest extends TestCase
{
    public function test_combined_copies_are_null_and_file_indexes_do_not_matter(): void
    {
        $normalizer = new OrderConfigurationNormalizer;
        $first = $this->preview([
            $this->file('individual', '1'),
            $this->file('combined-a', '1'),
            $this->file('combined-b', null),
        ]);
        $second = $this->preview([
            $this->file('combined-b', 1),
            $this->file('individual', 1),
            $this->file('combined-a'),
        ]);

        $this->assertSame(
            $normalizer->normalizeOrderConfiguration($first),
            $normalizer->normalizeOrderConfiguration($second),
        );
        $normalized = $normalizer->normalizeOrderConfiguration($first);
        $this->assertNull($normalized['file_details']['combined-a']['copies']);
        $this->assertNull($normalized['file_details']['combined-b']['copies']);
        $this->assertSame(1, $normalized['file_details']['individual']['copies']);
    }

    public function test_boolean_and_integer_representations_are_equivalent(): void
    {
        $normalizer = new OrderConfigurationNormalizer;
        $quoted = $this->preview([$this->file('individual', '1', '0')]);
        $confirmed = $this->preview([$this->file('individual', 1, false)]);

        $this->assertSame(
            $normalizer->normalizeOrderConfiguration($quoted),
            $normalizer->normalizeOrderConfiguration($confirmed),
        );
    }

    public function test_composition_order_remains_significant(): void
    {
        $normalizer = new OrderConfigurationNormalizer;
        $quoted = $normalizer->normalizeOrderConfiguration($this->preview([], ['combined-a', 'combined-b']));
        $reordered = $normalizer->normalizeOrderConfiguration($this->preview([], ['combined-b', 'combined-a']));

        $this->assertNotSame($quoted, $reordered);
    }

    private function preview(array $files, array $combinedIds = ['combined-a', 'combined-b']): array
    {
        return [
            'service_id' => '4', 'pages' => '2', 'copies' => 0, 'file_details' => $files,
            'image_composition' => [
                'file_ids' => $combinedIds, 'images_per_page' => '2', 'resulting_pages' => 1,
                'copies' => '1', 'billable_pages' => 1, 'instructions' => '  ',
            ],
            'print_units' => 2, 'urgent' => 0, 'manual_quote' => false,
            'calculation_status' => 'success', 'service_requires_quote' => false,
            'delivery_requires_review' => false, 'quantity' => null, 'variant' => null, 'addons' => [],
        ];
    }

    private function file(string $id, mixed $copies = null, mixed $duplex = false): array
    {
        return [
            'client_id' => $id, 'document_type' => 'image', 'source_pages' => '1',
            'copies' => $copies, 'paper_size' => ' carta ', 'double_sided' => $duplex,
            'stapled' => null, 'print_units' => $id === 'individual' ? 1 : 0,
            'included_in_composition' => str_starts_with($id, 'combined'),
        ];
    }
}
