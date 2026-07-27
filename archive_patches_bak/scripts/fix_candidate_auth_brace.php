<?php

/**
 * fix_candidate_auth_brace.php
 * Removes the extra closing brace that closes register() too early.
 */

define('ROOT', __DIR__);

$path = ROOT . '/app/Http/Controllers/CandidateAuthController.php';
if (!file_exists($path)) { echo "❌ File not found.\n"; exit(1); }

$bak = $path . '.bak';
$i = 2;
while (file_exists($bak)) { $bak = $path . '.bak' . $i++; }
copy($path, $bak);
echo "  [bak] $bak\n";

$old = <<<'PHP'
            if (!$jobPostingLocation) {
                return back()
                    ->withInput()
                    ->withErrors(['job_posting_id' => 'Sorry, that place of assignment is no longer available. Please choose another option.']);
            }
        }
        }

        $candidate = Candidate::create([
PHP;

$new = <<<'PHP'
            if (!$jobPostingLocation) {
                return back()
                    ->withInput()
                    ->withErrors(['job_posting_id' => 'Sorry, that place of assignment is no longer available. Please choose another option.']);
            }
        }

        $candidate = Candidate::create([
PHP;

$content = file_get_contents($path);
$count = substr_count($content, $old);

if ($count === 0) {
    echo "❌ Pattern not found. Trying line-based fix...\n";
    // Nuclear fallback: find the double }} and fix it
    $fixed = str_replace("            }\n        }\n\n        \$candidate", "            }\n        }\n\n        \$candidate", $content);
    if ($fixed === $content) {
        // Try removing the lone extra brace before $candidate
        $fixed = preg_replace('/(\s+\})\s*\n\s*\n\s*\$candidate/', "\n\n        \$candidate", $content);
    }
    if ($fixed === $content) {
        echo "❌ Could not fix automatically. Remove the extra } on line 73 manually.\n";
        exit(1);
    }
    file_put_contents($path, $fixed);
    echo "  [ok] Fixed via fallback.\n";
} else {
    file_put_contents($path, str_replace($old, $new, $content));
    echo "  [ok] Extra closing brace removed.\n";
}

echo "\nDone. Delete this script.\n";
