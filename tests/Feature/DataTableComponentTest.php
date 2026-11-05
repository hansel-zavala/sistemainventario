<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DataTableComponentTest extends TestCase
{
    #[TestWith([['name' => 'Nombre', 'status' => 'Estado'], ['name'], 2])]
    #[TestWith([['inventory' => 'Inventario', 'device' => 'Equipo', 'brand' => 'Marca'], ['device', 'brand'], 3])]
    public function test_each_configuration_controls_headers_and_column_options(array $columns, array $visibleColumns, int $headerCount): void
    {
        $view = $this->blade('<x-data-table id="sample" :columns="$columns" :visible-columns="$visibleColumns" class="custom-table"><tr><td>Contenido propio</td></tr></x-data-table>', compact('columns', 'visibleColumns'));
        $xpath = $this->xpath((string) $view);

        $this->assertSame($headerCount, $xpath->query('//thead//th')->length);
        $this->assertSame(count($columns), $xpath->query('//input[@type="checkbox"]')->length);
        foreach ($columns as $key => $label) {
            $headers = $xpath->query('//th[@*[name()="wire:key"]="sample-heading-'.$key.'"]');
            $this->assertSame(in_array($key, $visibleColumns, true) ? 1 : 0, $headers->length);
            if ($headers->length) {
                $this->assertStringContainsString($label, $headers->item(0)->textContent);
            }
        }
        $this->assertSame(count($visibleColumns) === 1 ? 1 : 0, $xpath->query('//input[@type="checkbox" and @disabled]')->length);
        $view->assertSee('Contenido propio')->assertSee('Acciones')->assertSee('custom-table');
    }

    public function test_optional_controls_and_actions_can_be_omitted(): void
    {
        $view = $this->blade('<x-data-table id="readonly" :columns="[\'name\' => \'Nombre\']" :show-search="false" :show-columns="false" :sortable="false" :show-actions="false"><tr><td>Solo lectura</td></tr></x-data-table>');
        $xpath = $this->xpath((string) $view);

        $this->assertSame(1, $xpath->query('//thead//th')->length);
        $this->assertSame(0, $xpath->query('//button | //input')->length);
        $view->assertSee('Nombre')->assertSee('Solo lectura')->assertDontSee('Acciones')->assertDontSee('Exportar');
    }

    public function test_slots_sorting_and_identifiers_belong_to_the_configured_table(): void
    {
        $template = <<<'BLADE'
            <x-data-table :id="$id" :columns="['name' => 'Nombre']" sort-column="name" sort-direction="desc" search-label="Buscar marca" search-placeholder="Nombre de marca...">
                <x-slot:filters><label>Filtro propio</label></x-slot:filters>
                <x-slot:export><form><button>Descargar</button></form></x-slot:export>
                <x-slot:summary>2 marcas</x-slot:summary>
                <tr><td>Marca propia</td></tr>
                <x-slot:pagination><nav>Página 1</nav></x-slot:pagination>
            </x-data-table>
            BLADE;

        foreach (['marcas', 'departamentos'] as $id) {
            $view = $this->blade($template, compact('id'));
            $xpath = $this->xpath((string) $view);

            $this->assertSame(1, $xpath->query('//input[@id="'.$id.'-search"]')->length);
            $this->assertSame(1, $xpath->query('//div[@id="'.$id.'-columns"]')->length);
            $this->assertSame(1, $xpath->query('//div[@id="'.$id.'-export"]')->length);
            $this->assertSame(1, $xpath->query('//th[@aria-sort="descending"]')->length);
            $view->assertSee('data-storage-key="'.$id.'.visibleColumns"', false)
                ->assertSee('Filtro propio')->assertSee('Descargar')->assertSee('2 marcas')->assertSee('Marca propia')->assertSee('Página 1')
                ->assertSee('Buscar marca')->assertSee('Nombre de marca...')->assertSee('Exportar')
                ->assertDontSee('equipos.visibleColumns');
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
