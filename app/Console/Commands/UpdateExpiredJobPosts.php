<?php

namespace App\Console\Commands;

use App\Models\JobPost;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateExpiredJobPosts extends Command
{
     // The name and signature of the console command
     protected $signature = 'jobposts:update-expired-status';

     // The console command description
     protected $description = 'Update job posts to Inactive where the application deadline has passed';
 
     // Execute the console command
     public function handle()
     {
         // Get the current date and time
         $now = Carbon::now();
 
         // Find job posts where application deadline has passed and are still Active
         $expiredJobs = JobPost::where('status', 'Active')
             ->where('application_deadline', '<', $now)
             ->get();
 
         foreach ($expiredJobs as $job) {
             // Update the job status to 'Inactive'
             $job->update(['status' => 'Inactive']);
             $this->info("Job post ID {$job->id} updated to Inactive.");
         }
 
         $this->info('Expired job posts have been updated successfully.');
     }
}
