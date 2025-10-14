<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];
  public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,

        ];


        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
            $response['message'] = $error;
        }


        return response()->json($response, $code);
    }
    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
		    if($exception instanceof ModelNotFoundException)
            {

                return $this->sendError('#550', "Server Internal Error : ModelNotFoundException");
            }

            if($exception instanceof NotFoundHttpException )
            {

               return $this->sendError('#550', "Server Internal Error : NotFoundHttpException");
            }
            if($exception instanceof InvalidArgumentException )
            {
            //    return Response()->json('fffffffffff',404);
               return $this->sendError('#550', "Server Internal Error : InvalidArgumentException ");
            }
        if($exception instanceof AuthenticationException ) {

            return $this->sendError('#204', "Server Internal Error :Unauthenticated");
        }
        if ($exception instanceof PDOException){
            

            return $this->sendError('#550', "Server Internal Error :".$exception->getMessage());

        }
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
	 public function render($request, Throwable $exception)
    {


        
        return parent::render($request, $exception);
    }
  /*  public function render($request, Throwable $exception)
    {
        return parent::render($request, $exception);
    }*/
}
