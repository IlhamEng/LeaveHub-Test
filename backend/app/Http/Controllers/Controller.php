<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="LeaveHub API",
 *     description="Leave Request Management API Documentation",
 *     @OA\Contact(
 *         email="admin@leavehub.com"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Enter your Sanctum token"
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 */
abstract class Controller
{
    //
}
