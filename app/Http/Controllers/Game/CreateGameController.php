<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\CreateGameRequest;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CreateGameController extends Controller
{
    public function __construct(private GameService $gameService) {}

    /**
     * Handle the incoming request.
     *
     * @param CreateGameRequest $request
     * @return JsonResponse
     */
    public function __invoke(CreateGameRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->get('userName')
        ]);
        Auth::login($user);
        return response()->json([
            'user' => $user,
            'game' => $this->gameService->createGame($user, $request->get('expansionIds'))
        ]);
    }
}
