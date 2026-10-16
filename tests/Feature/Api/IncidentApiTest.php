<?php

use App\Models\Category;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->category = Category::create(['name' => 'Hardware']);
    $this->user = User::factory()->create([
        'name' => 'Usuario de prueba',
        'email' => 'usuario@example.test',
        'password' => Hash::make('password123'),
        'role' => 'usuario',
    ]);
    $this->technician = User::factory()->create([
        'name' => 'Técnico de prueba',
        'email' => 'tecnico@example.test',
        'password' => Hash::make('password123'),
        'role' => 'tecnico',
    ]);
});

test('login entrega un token de Sanctum y el perfil no expone la contraseña', function (): void {
    $login = $this->postJson('/api/login', [
        'email' => 'usuario@example.test',
        'password' => 'password123',
    ]);

    $login->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.role', 'usuario')
        ->assertJsonMissingPath('user.password');

    $this->withToken($login->json('token'))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', 'usuario@example.test');
});

test('un usuario crea incidencias abiertas y no puede consultar incidencias ajenas', function (): void {
    $create = $this->actingAs($this->user, 'sanctum')->postJson('/api/incidencias', [
        'title' => 'La computadora no enciende',
        'description' => 'La computadora del aula no enciende desde esta mañana.',
        'category_id' => $this->category->id,
        'status' => 'RESUELTA',
        'user_id' => $this->technician->id,
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.status', Incident::STATUS_OPEN)
        ->assertJsonPath('data.user_id', $this->user->id)
        ->assertJsonPath('data.technician_id', null);

    $privateIncident = Incident::create([
        'title' => 'Incidencia privada',
        'description' => 'Esta incidencia pertenece a otra persona.',
        'category_id' => $this->category->id,
        'user_id' => $this->technician->id,
        'status' => Incident::STATUS_OPEN,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/incidencias')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/incidencias/'.$privateIncident->id)
        ->assertNotFound();
});

test('un técnico toma una incidencia abierta y solo el asignado puede resolverla', function (): void {
    $incident = Incident::create([
        'title' => 'Falla de red',
        'description' => 'El equipo no tiene acceso a Internet.',
        'category_id' => $this->category->id,
        'user_id' => $this->user->id,
        'status' => Incident::STATUS_OPEN,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson('/api/incidencias/'.$incident->id.'/tomar')
        ->assertForbidden();

    $this->actingAs($this->technician, 'sanctum')
        ->putJson('/api/incidencias/'.$incident->id.'/tomar')
        ->assertOk()
        ->assertJsonPath('data.status', Incident::STATUS_IN_PROGRESS)
        ->assertJsonPath('data.technician_id', $this->technician->id);

    $otherTechnician = User::factory()->create(['role' => 'tecnico']);
    $this->actingAs($otherTechnician, 'sanctum')
        ->putJson('/api/incidencias/'.$incident->id.'/resolver', [
            'resolution' => 'Se reinició el equipo y volvió a funcionar.',
        ])
        ->assertForbidden();

    $this->actingAs($this->technician, 'sanctum')
        ->putJson('/api/incidencias/'.$incident->id.'/resolver', ['resolution' => '   '])
        ->assertUnprocessable();

    $this->putJson('/api/incidencias/'.$incident->id.'/resolver', [
        'resolution' => 'Se reinició el equipo y volvió a funcionar.',
    ])->assertOk()
        ->assertJsonPath('data.status', Incident::STATUS_RESOLVED)
        ->assertJsonPath('data.resolution', 'Se reinició el equipo y volvió a funcionar.');
});

test('los comentarios guardan al autor y aparecen en orden cronológico', function (): void {
    $incident = Incident::create([
        'title' => 'Impresora desconectada',
        'description' => 'La impresora de administración no responde.',
        'category_id' => $this->category->id,
        'user_id' => $this->user->id,
        'status' => Incident::STATUS_OPEN,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/incidencias/'.$incident->id.'/comentarios', [
            'comment' => 'El problema empezó hoy por la mañana.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $this->user->id);

    $this->getJson('/api/incidencias/'.$incident->id.'/comentarios')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.comment', 'El problema empezó hoy por la mañana.');
});
