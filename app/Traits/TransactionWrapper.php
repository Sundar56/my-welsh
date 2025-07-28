<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait TransactionWrapper
{
    /**
     * Execute the given callback within a database transaction.
     *
     * This ensures that all database operations inside the callback are atomic —
     * meaning they either all succeed or all fail together. If an exception is
     * thrown during the callback execution, the transaction is rolled back.
     *
     * @param callable $callback The logic to execute within the transaction.
     *
     * @return mixed The result returned by the callback.
     *
     * @throws \Throwable Any exception thrown during the callback execution.
     */
    public function runInTransaction(callable $callback)
    {
        DB::beginTransaction();
        try {
            $result = $callback();
            DB::commit();
            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->transactionErrorResponse($e->getMessage(), 500);
        }
    }
    /**
     * Returns a formatted transaction error response.
     *
     * @param string $errorMessage
     *
     * @return array
     */
    protected function transactionErrorResponse($message, $statusCode = 500): array
    {
        return [
            'status' => false,
            'data' => [],
            'message' => $message,
            'statusCode' => $statusCode,
        ];
    }
}
