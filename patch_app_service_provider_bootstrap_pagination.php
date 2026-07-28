<?php
/**
 * Patch: Laravel's default paginator view uses Tailwind CSS classes,
 * which do nothing in this Bootstrap 5 project — so the Previous/Next
 * pagination links render as raw, unstyled anchors and stack as two
 * oversized full-width blocks instead of small inline buttons.
 * Paginator::useBootstrapFive() switches the ->links() output to
 * Bootstrap 5 markup (.pagination / .page-item / .page-link), which the
 * project's existing Bootstrap CSS already styles correctly.
 *
 * Run once from the project root:
 *   php patch_app_service_provider_bootstrap_pagination.php
 * Then delete this file.
 */

function apply_patch(string $path, array $edits): void
{
    if (!file_exists($path)) {
        fwrite(STDERR, "ABORT: file not found: $path\n");
        exit(1);
    }

    $original = file_get_contents($path);
    $working = $original;

    foreach ($edits as $i => [$search, $replace, $label]) {
        $count = substr_count($working, $search);
        if ($count !== 1) {
            fwrite(STDERR, "ABORT: edit #$i ($label) matched $count times (expected exactly 1) in $path\n");
            fwrite(STDERR, "No changes were written.\n");
            exit(1);
        }
        $working = str_replace($search, $replace, $working);
    }

    $backup = $path . '.bak';
    if (!copy($path, $backup)) {
        fwrite(STDERR, "ABORT: could not create backup at $backup\n");
        exit(1);
    }

    file_put_contents($path, $working);
    echo "Patched: $path\n";
    echo "Backup:  $backup\n";
}

// ── Adjust this path if your project layout differs ────────────────────
$file = __DIR__ . '/app/Providers/AppServiceProvider.php';

apply_patch($file, [
    [
        "use Illuminate\Support\ServiceProvider;\nuse App\Models\ActivityLog;",
        "use Illuminate\Support\ServiceProvider;\nuse Illuminate\Pagination\Paginator;\nuse App\Models\ActivityLog;",
        'import Paginator',
    ],
    [
        <<<'OLD'
    public function boot(): void
    {
        // Activity Log Book: record create/update/delete on core HR models.
OLD,
        <<<'NEW'
    public function boot(): void
    {
        // Laravel's default paginator view uses Tailwind classes, which
        // this Bootstrap 5 project doesn't load — without this, ->links()
        // renders unstyled Previous/Next anchors that stack as two
        // oversized full-width blocks instead of small inline buttons.
        Paginator::useBootstrapFive();

        // Activity Log Book: record create/update/delete on core HR models.
NEW,
        'add Paginator::useBootstrapFive() call',
    ],
]);

echo "\nDone. Reload the Job Postings or Applications list to confirm the\n";
echo "pagination now renders as small Bootstrap buttons before deleting\n";
echo "this script and its .bak backup.\n";
