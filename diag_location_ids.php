<?php
$posting = App\Models\JobPosting::where('title', 'Nurse II')->first();
echo 'Posting ID: ' . $posting->id . PHP_EOL;
foreach ($posting->locations as $loc) {
    echo 'Location: ' . $loc->id . ' = ' . $loc->place_of_assignment . PHP_EOL;
}
foreach (App\Models\Application::where('job_posting_id', $posting->id)->get() as $app) {
    echo 'App: ' . $app->candidate->full_name . ' -> job_posting_location_id = ' . var_export($app->job_posting_location_id, true) . PHP_EOL;
}
