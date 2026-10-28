<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ExcelProductWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_endpoints_require_authentication(): void
    {
        $this->getJson('/api/products/import/template')->assertUnauthorized();
        $this->postJson('/api/products/import/preview')->assertUnauthorized();
        $this->getJson('/api/products/export')->assertUnauthorized();
        $this->getJson('/api/stock-counts/export')->assertUnauthorized();
    }

    public function test_product_import_template_is_a_single_sheet_xlsx(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $response = $this->get('/api/products/import/template')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
        $this->assertStringContainsString('products-template.xlsx', $response->headers->get('content-disposition'));
        $rows = $this->readDownload($response);
        $this->assertCount(1, $rows);
        $this->assertSame(['sku', 'name', 'part_number', 'base_unit', 'min_stock_quantity', 'category_name'], $rows[0][0]);
    }

    public function test_preview_reports_duplicate_and_invalid_rows_without_writing_then_confirm_imports_valid_rows(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        DB::table('products')->insert([
            'sku' => 'EXISTING-1', 'name' => 'Existing', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $file = $this->workbook([
            ['sku', 'name', 'part_number', 'base_unit', 'min_stock_quantity', 'category_name'],
            ['NEW-1', 'ไส้กรอง', 'FLT-1', 'ชิ้น', '2.5', 'กรอง'],
            ['existing-1', 'ซ้ำในระบบ', '', 'ชิ้น', '0', ''],
            ['NEW-1', 'ซ้ำในไฟล์', '', 'ชิ้น', '0', ''],
            ['', 'ไม่มี SKU', '', 'ชิ้น', '0', ''],
        ]);

        $response = $this->post('/api/products/import/preview', ['file' => $file])->assertOk();
        $response->assertJsonPath('valid_count', 1)->assertJsonPath('error_count', 3);
        $this->assertDatabaseCount('products', 1);

        $this->postJson('/api/products/import/confirm', ['token' => $response->json('token')])
            ->assertCreated()->assertJsonPath('imported_count', 1);
        $this->assertDatabaseHas('products', ['sku' => 'NEW-1', 'name' => 'ไส้กรอง']);
        $this->assertDatabaseHas('categories', ['name' => 'กรอง']);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.imported', 'entity_type' => 'product']);
        $this->postJson('/api/products/import/confirm', ['token' => $response->json('token')])->assertUnprocessable();
    }

    public function test_staff_cannot_import_or_export_products(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/api/products/import/template')->assertForbidden();
        $this->post('/api/products/import/preview', ['file' => $this->workbook([
            ['sku', 'name', 'part_number', 'base_unit', 'min_stock_quantity', 'category_name'],
        ])])->assertForbidden();
        $this->get('/api/products/export')->assertForbidden();
    }

    public function test_import_preview_token_is_bound_to_the_user_that_created_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $preview = $this->post('/api/products/import/preview', ['file' => $this->workbook([
            ['sku', 'name', 'part_number', 'base_unit', 'min_stock_quantity', 'category_name'],
            ['BOUND-1', 'ผ้าเบรก', '', 'คู่', '0', ''],
        ])])->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->postJson('/api/products/import/confirm', ['token' => $preview->json('token')])->assertUnprocessable();
        $this->assertDatabaseMissing('products', ['sku' => 'BOUND-1']);
    }

    public function test_product_and_count_result_exports_are_excel_downloads(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $product = $this->createProduct('EXPORT-1');
        $productExport = $this->get('/api/products/export')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $productRows = $this->readDownload($productExport);
        $this->assertSame('EXPORT-1', (string) $productRows[0][1][0]);

        $countId = DB::table('stock_counts')->insertGetId(['status' => 'open', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $itemId = DB::table('stock_count_items')->insertGetId([
            'stock_count_id' => $countId, 'product_id' => $product, 'reference_quantity' => 10,
            'reference_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stock_count_attempts')->insert([
            ['stock_count_item_id' => $itemId, 'attempt_number' => 1, 'counted_quantity' => 8, 'counted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['stock_count_item_id' => $itemId, 'attempt_number' => 2, 'counted_quantity' => 9, 'counted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get('/api/stock-counts/export?count_id='.$countId)->assertOk();
        $this->assertStringContainsString('count-results-'.$countId.'.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
        $rows = $this->readDownload($response);
        $this->assertCount(3, $rows[0]);
        $this->assertSame('1', (string) $rows[0][1][5]);
        $this->assertSame('8.000', (string) $rows[0][1][6]);
        $this->assertSame('-2.000', (string) $rows[0][1][8]);
        $this->assertSame('2', (string) $rows[0][2][5]);
        $this->assertSame('9.000', (string) $rows[0][2][6]);
        $this->assertSame('-1.000', (string) $rows[0][2][8]);
    }

    private function workbook(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'partshelf-xlsx-');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
        $content = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('products.xlsx', $content);
    }

    private function createProduct(string $sku): int
    {
        return DB::table('products')->insertGetId([
            'sku' => $sku, 'name' => 'ส่งออกทดสอบ', 'base_unit' => 'ชิ้น', 'min_stock_quantity' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function readWorkbook(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'partshelf-xlsx-read-');
        file_put_contents($path, $contents);
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($cell) => $cell->getValue(), $row->cells);
            }
            $sheets[] = $rows;
        }
        $reader->close();
        @unlink($path);

        return $sheets;
    }

    private function readDownload($response): array
    {
        return $this->readWorkbook(file_get_contents($response->baseResponse->getFile()->getPathname()));
    }
}
