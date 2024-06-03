<?php

namespace Darkterminal\LibSQLDriver\Database;

use Illuminate\Database\Query\Processors\SQLiteProcessor;

class LibSQLQueryProcessor extends SQLiteProcessor
{
    /**
     * Process the list of tables.
     *
     * @param  mixed  $results
     * @return array
     */
    public function processTables($results): array
    {
        // Ensure $results is an array
        $results = (array) $results;

        return array_map(function ($result) {
            return [
                'name' => $result['name'],
            ];
        }, $results);
    }
}
