<?php

namespace Tests\Feature\Http\Controllers\Game;

use App\Models\Expansion;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateGameTest extends TestCase
{

    /** @test */
    public function it_does_not_allow_empty_user_name()
    {
        $expansionIds = Expansion::take(3)->get()->pluck('id');
        $this->postJson(route('api.game.store'), [
            'name' => "",
            'expansionIds' => $expansionIds->toArray()
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @test */
    public function it_does_not_allow_user_to_select_no_expansions()
    {
        $user = User::factory()->make();
        $this->postJson(route('api.game.store'), [
            'name' => $user->name,
            'expansionIds' => []
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @test */
    public function it_does_not_allow_undefined_expansions()
    {
        $user = User::factory()->make();
        $this->postJson(route('api.game.store'), [
            'name' => $user->name,
            'expansionIds' => [-1]
        ])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @test */
    public function it_creates_user()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $this->postJson(route('api.game.store'), [
            'name' => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'name' => $userName
        ]);
    }

    /** @test */
    public function it_creates_game()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $response = $this->postJson(route('api.game.store'), [
            'name' => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk();

        $this->assertDatabaseHas('games', [
            'id' => $response->json('data.id'),
            'name' => $response->json('data.name'),
            'code' => $response->json('data.code'),
        ]);

        $this->assertEquals(4, strlen($response->json('data.code')));
    }

    /** @test */
    public function it_assigns_users_when_game_is_created()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::first()->pluck('id');
        $response = $this->postJson(route('api.game.store'), [
            'name' => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk();

        $this->assertDatabaseHas('game_users', [
            'game_id' => $response->json('data.id'),
            'user_id' => $response->json('data.current_user.id')
        ]);
    }

    /** @test */
    public function it_gives_users_cards_when_a_game_is_created()
    {
        $userName = $this->faker->userName;
        $expansionId = Expansion::query()->orderByDesc('id')->first()->id;

        $this->postJson(route('api.game.store'), [
            'name' => $userName,
            'expansionIds' => [$expansionId]
        ])->assertOk();
        $createdUser = User::where('name', $userName)->first();

        $this->assertCount(7, $createdUser->whiteCards);
        $createdUser->whiteCards->each(function ($item) use ($expansionId) {
            $this->assertEquals($expansionId, $item->expansion_id);
        });
    }

    /** @test */
    public function it_assigns_selected_expansions_when_game_is_created()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $response = $this->postJson(route('api.game.store'), [
            'name' => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk();

        $expansionIds->each(fn($id) => $this->assertDatabaseHas('game_expansions', [
            'game_id' => $response->json('data.id'),
            'expansion_id' => $id
        ])
        );
    }
    /** @test */
    public function it_creates_gamecode_with_uppercase_letters()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $response = $this->postJson(route('api.game.store'), [
            'name' => $userName,
            'expansionIds' => $expansionIds->toArray()
        ]);

        $gameCode = $response->json('data.code');
        $this->assertEquals(Str::upper($gameCode), $gameCode);

        $invalidCode = Str::lower($gameCode);
        $this->assertNotEquals($invalidCode, $gameCode);
    }

    /** @test */
    public function it_expects_certain_shape()
    {
        $userName = $this->faker->userName;
        $expansionIds = Expansion::take(1)->get()->pluck('id');
        $this->postJson(route('api.game.store'), [
            'name' => $userName,
            'expansionIds' => $expansionIds->toArray()
        ])->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'users' => [
                        [
                            'id',
                            'name',
                            'score'
                        ]
                    ],
                    'current_user' => [
                        'id',
                        'name',
                        'score'
                    ],
                    'judge' => [
                        'id',
                        'name',
                    ],
                    'hand' => [
                        [
                            'id',
                            'text',
                            'expansion_id'
                        ],
                    ],
                    'id',
                    'name',
                    'code',
                    'current_black_card' => [
                        'id',
                        'text',
                        'pick'
                    ]
                ]

            ]);
    }
}
