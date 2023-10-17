<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    /**
     * GET ALL PLAYERS WITH THEIR SUCCESS PERCENTAGES
     *
     * @group Admin
     *
     * @response 422 {
     *     "message": "No players have been played yet"
     * @response 200 {
     *     "message": "Players and success rates found",
     *      [
     *     "data": [
     *         {
     *             "nickname": "nickname1",
     *             "successRate": "16.66%"
     *         },
     *         {
     *             "nickname": "nickname2",
     *             "successRate": "14.66%"
     *         },
     *     ]
     * }
     */
    public function index()
    {
        // TODO Add permissions
        $users = User::all();
        $data = [];
        // if !$users, foreach does not execute
        foreach ($users as $user) {
            $userNickname = $user->nickname ?? 'Anonymous';
            $successRate = $user->calculatePlayerSuccessRate();
            $successRate = $user->games->count() ?  $successRate . '%' : "No games for this player";
            $data[] = [
                'nickname' => $userNickname,
                'successRate' => $successRate,
            ];
        }
        // Any games where found
        if (empty($data)) {
            return response()->json(
                ['message' => 'No players have been played yet'],
                422
            );
        }
        return response()->json(
            ['message' => 'Players and success rates found', 'data' => $data],
            200
        );
    }
    /**
     * CREATE A NEW GAME FOR A SPECIFIC USER.
     *
     * @group User
     * @urlParam user_id. The id of the user who is playing. Example: 1
     *
     * @response 201 {
     * "message": "game created successfully",
     *      "data": {
     *      "dice_1": 4,
     *      "dice_2": 3,
     *      "gameWon": "Won",
     *      "user_id": "1",
     *      "updated_at": "2023-10-15T15:09:02.000000Z",
     *      "created_at": "2023-10-15T15:09:02.000000Z",
     *      "id": 1
     *  }
     * }
     * @response 422 {
     *     "message": 'User not found',
     * }
     * @response 422 {
     *     "message": 'Validation failed',
     *     "error": "Different Laravel validation messages from lang/en/validation.php",
     * }
     * @response 401 {
     *     "error": "Unauthorized",
     * }
     */
    public function store(string $user_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(
                ['message' => 'User not found'],
                422
            );
        }
        $game = new Game();
        $game->dice_1 = $game->rollDice();
        $game->dice_2 = $game->rollDice();
        $input = [
            'dice_1' =>  $game->dice_1,
            'dice_2' =>  $game->dice_2,
            'gameWon' => $game->isGameWon(),
            'user_id' => $user_id,
        ];
        $validationRules = [
            'dice_1' => 'required|min:1|max:6',
            'dice_2' => 'required|min:1|max:6',
            'gameWon' => 'required|in:Won,Lost',
            'user_id' => 'required|numeric|exists:users,id',
        ];

        // Validate request inputs
        $validator = validator($input, $validationRules);

        if ($validator->fails()) {
            // Get a 422 response with validation errors
            return response()->json(
                ['message' => 'Validation failed', 'errors' => $validator->errors()],
                422
            );
        }
        // Check if user is authenticated with token
        $authUser = Auth::user();
        if ($authUser->id == $user_id) {
            $game = Game::create($input);
            return response()->json(
                ['message' => 'game created successfully', 'data' => $game],
                201
            );
        }
        return response()->json(
            ['error' => 'Unauthorized'],
            401
        );
    }

    /**
     * SHOW ALL GAMES PLAYED BY A SPECIFIC PLAYER.
     *
     * @group User
     *
     * @urlParam user_id The id of the player. Example: 1
     *
     * @response 422 {
     *     "message": "User not found"
     * }
     * @response 200 {
     *     "message": "The player has not played any games yet"
     * }
     * @response 200 {
     *     "message": "Games found",
     *     "data": [
     *         {
     *             "id": 1,
     *             "dice_1": 4,
     *             "dice_2": 3,
     *             "gameWon": "Won",
     *             "user_id": "1",
     *             "created_at": "2023-10-15T15:09:02.000000Z",
     *             "updated_at": "2023-10-15T15:09:02.000000Z"
     *         },
     *         {
     *             "id": 2,
     *             "dice_1": 2,
     *             "dice_2": 5,
     *             "gameWon": "Lost",
     *             "user_id": "1",
     *             "created_at": "2023-10-16T09:21:45.000000Z",
     *             "updated_at": "2023-10-16T09:21:45.000000Z"
     *         }
     *     ],
     *     "User success rate": "50.00%"
     * }
     * @response 401 {
     *     "error": "Unauthorized",
     * }
     */
    public function showPlayerGames(string $user_id)
    {
        $user = User::find($user_id);

        if (!$user) {
            return response()->json(
                ['message' => 'User not found'],
                422
            );
        }
        // Check if user is authenticated with token
        $authUser = Auth::user();
        if ($authUser->id == $user_id) {
            // Using eloquent relationship to bring all games played by a specific user
            $games = $user->games;
            if ($games->isEmpty()) {
                return response()->json(
                    ['message' => 'The player has not played any games yet'],
                    200
                );
            }
            $succesRate = $user->calculatePlayerSuccessRate();
            return response()->json(
                ['message' => 'Games found', 'Games' => $games, 'User success rate' => $succesRate . '%'],
                200
            );
        }
        return response()->json(
            ['error' => 'Unauthorized'],
            401
        );
    }





    /**
     * GET PLAYERS RANKING ORDERED BY SUCCESS RATE PERCENTAGES
     *
     * @group Admin
     *
     * @response 422 {
     *     "message": "No players have been played yet"
     * @response 200 {
     *     "message": "Player and success rates found",
     *      [
     *     "User data": [
     *         {
     *             "nickname": "nickname1",
     *             "successRate": "16.66%"
     *         },
     *         {
     *             "nickname": "nickname2",
     *             "successRate": "14.66%"
     *         },
     *     ],
     *     "Average success rate": "14.81%"
     * }
     */

    public function ranking()
    {
        // TODO Add permissions
        $users = User::all();
        $playersPlayedData = [];
        $playersNotPlayedData = [];
        // if !$users, foreach does not execute and returns "No players have played yet"
        foreach ($users as $user) {
            $userNickname = $user->nickname ?? 'Anonymous';
            if ($user->games->count()) {
                $playersPlayedData[] = [
                    'nickname' => $userNickname,
                    'successRate' => $user->calculatePlayerSuccessRate() . '%',
                    'gamesPlayed' => $user->games->count(),
                ];
            } else {
                $playersNotPlayedData[] = [
                    'nickname' => $userNickname,
                    'successRate' => "The player have not played yet",
                    'gamesPlayed' => $user->games->count(),
                ];
            }
        }
        // Order players by success rate and if there is a tie, by games played
        array_multisort(
            array_column($playersPlayedData, 'successRate'),
            SORT_DESC,
            array_column($playersPlayedData, 'gamesPlayed'),
            SORT_ASC,
            $playersPlayedData
        );

        $playersData[] = array_merge($playersPlayedData, $playersNotPlayedData);

        // Any games where found
        if (empty($playersPlayedData)) {
            return response()->json(
                ['message' => 'No players have played yet'],
                422
            );
        }
        $generalSuccessRate = $this->calculateGeneralSuccessRate() . '%';
        return response()->json(
            [
                'message' => 'Players and success rates found', 'Players data' => $playersData, 'Average success rate' => $generalSuccessRate
            ],
            200
        );
    }
    /**
     * GET THE PLAYER WITH WORST SUCCESS PERCENTAGES
     *
     * @group Admin
     *
     * @response 422 {
     *     "message": "No players have played yet"
     * @response 200 {
     *     "message": "'The worst player is loserNickname with a success rate of 10%",
     * }
     */
    public function showLoser()
    {
        $loser = $this->getLoser();
        $gotUser = is_object($loser);
        if ($gotUser) {
            $loserNickname = $loser->nickname ?? 'Anonymous';
            $successRate = $loser->calculatePlayerSuccessRate() . '%';
            return response()->json(
                [
                    'message' => 'The worst player is ' . $loserNickname . ' with a success rate of ' . $successRate
                ],
                200
            );
        }
        //GET a 422 response from getloser().
        // TODO test this output
        return $loser;
    }
    /**
     * GET THE PLAYER WITH BEST SUCCESS PERCENTAGES
     *
     * @group Admin
     *
     * @response 422 {
     *     "message": "No players have played yet"
     * @response 200 {
     *     "message": "'The best player is winnerNickname with a success rate of 10%",
     * }
     */
    public function showWinner()
    {
        $winner = $this->getWinner();
        $gotUser = is_object($winner);
        if ($gotUser) {
            $winnerNickname = $winner->nickname ?? 'Anonymous';
            $successRate = $winner->calculatePlayerSuccessRate() . '%';
            return response()->json(
                [
                    'message' => 'The best player is ' . $winnerNickname . ' with a success rate of ' . $successRate
                ],
                200
            );
        }
        //GET a 422 response from getWinner().
        // TODO test this output
        return $winner;
    }
    /**
     * REMOVE GAMES FROM A SPECIFIC PLAYER.
     *
     * @group User
     * @urlParam user_id. The id of the user who is deleting. Example: 1
     *
     * @response 422 {
     *     "error": "User not found"
     * }
     * @response 200 {
     *      "message": "The player has not played any games yet",
     * }
     * @response 200 {
     *      "message": "The games of the player have been deleted",
     * }
     * @response 401 {
     *     "error": "Unauthorized",
     * }
     */
    public function destroyPlayerGames(string $user_id)
    {
        $user = User::find($user_id);

        if (!$user) {
            return response()->json(
                ['error' => 'User not found'],
                422
            );
        }
        // Check if user is authenticated with token
        $authUser = Auth::user();
        if ($authUser->id == $user_id) {
            // Using eloquent relationship to bring all games played by this specific user.
            $games = $user->games;
            // Check if players haven't played any games yet or deleted previously.
            if ($games->isEmpty()) {
                return response()->json(
                    ['message' => 'The player has not played any games yet'],
                    200
                );
            }
            // Delete all games of this specific user one by one
            foreach ($games as $game) {
                $game->delete();
            }
            return response()->json(
                ['message' => 'The games of the player have been deleted'],
                200
            );
        }
        return response()->json(
            ['error' => 'Unauthorized'],
            401
        );
    }
    /**----------------- SERVICES METHODS-----------------*/
    /**
     * Calculate success rate of all players
     * @return float
     */
    public function calculateGeneralSuccessRate(): float
    {
        // Get number of games won at games table
        $GamesWon = Game::where('gameWon', 'Won')->count();
        // Get number of games played at games table
        $Games = Game::count();
        if ($Games == 0) {
            return 0.0;
        }
        $successRate = number_format($GamesWon / $Games * 100, 2);
        $successRate = number_format($successRate, 2);

        return $successRate;
    }
    /**
     * Gets the user (who has played) with the worst success rate
     * @return object User
     */
    public function getLoser(): object
    {
        // TODO Add permissions
        $users = User::all();
        $loser = $users->first();
        $gamesPlayed = false;
        // if !$users, foreach does not execute and returns "No players have played yet"
        foreach ($users as $user) {
            if ($user->games->count() === 0) {
                continue;
            } else {
                $gamesPlayed = true;
            }
            if ($user->calculatePlayerSuccessRate() < $loser->calculatePlayerSuccessRate()) {
                $loser = $user;
            }
        }
        if (!$gamesPlayed) {
            return response()->json(
                ['message' => 'No players have played yet'],
                422
            );
        }
        return $loser;
    }
    /**
     * Gets the user (who has played) with the best success rate
     * @return object User
     */
    public function getWinner()
    {
        // TODO Add permissions
        $users = User::all();
        $loser = $users->first();
        $gamesPlayed = false;
        // if !$users, foreach does not execute and returns "No players have played yet"
        foreach ($users as $user) {
            if ($user->games->count() === 0) {
                continue;
            } else {
                $gamesPlayed = true;
            }
            if ($user->calculatePlayerSuccessRate() > $loser->calculatePlayerSuccessRate()) {
                $loser = $user;
            }
        }
        if (!$gamesPlayed) {
            return response()->json(
                ['message' => 'No players have played yet'],
                422
            );
        }
        return $loser;
    }
}
