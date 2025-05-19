<?php
// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample_subscribers.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write header row
fputcsv($output, ['email', 'name', 'group_id']);

// Write sample data
fputcsv($output, ['subscriber1@example.com', 'John Doe', '1']);
fputcsv($output, ['subscriber2@example.com', 'Jane Smith', '2']);
fputcsv($output, ['subscriber3@example.com', 'Bob Johnson', '']);

// Close output stream
fclose($output);
?>