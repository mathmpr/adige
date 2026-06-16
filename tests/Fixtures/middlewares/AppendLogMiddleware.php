<?php

namespace Tests\Fixtures\middlewares;

use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\middleware\Middleware;

class AppendLogMiddleware extends Middleware
{
    public function __construct(private readonly string $label)
    {
        parent::__construct();
    }

    public function handle(BaseRequest $request, ?BaseResponse $response): ?BaseResponse
    {
        $input = $request->input();
        $log = $input['log'] ?? [];
        $log[] = $this->label;
        $input['log'] = $log;
        $request->setInput($input);

        return null;
    }
}
