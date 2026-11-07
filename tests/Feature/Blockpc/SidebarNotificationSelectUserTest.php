<?php

declare(strict_types=1);

use Blockpc\App\Jobs\SendNotificationToUserJob;
use Blockpc\App\Livewire\Notifications\SidebarNotification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses()->group('notifications');

it('select_user asigna el destinatario y permite enviar la notificación', function () {
    Queue::fake();
    $sender = new_user();
    $recipient = new_user();

    Livewire::actingAs($sender)
        ->test(SidebarNotification::class)
        ->call('select_user', $recipient->id)
        ->assertSet('notification_user_id', $recipient->id)
        ->assertSet('select_user_id', $recipient->id)
        ->assertSet('select_user_name', $recipient->fullname)
        ->set('response_type_id', 'info')
        ->set('response_message', 'Hola')
        ->call('send_new_notification')
        ->assertHasNoErrors();

    Queue::assertPushed(SendNotificationToUserJob::class, fn ($job) => $job->user_id === $recipient->id);
});

it('select_user ignora usuarios inexistentes o el propio usuario', function () {
    $sender = new_user();

    Livewire::actingAs($sender)
        ->test(SidebarNotification::class)
        ->call('select_user', $sender->id)
        ->assertSet('notification_user_id', null)
        ->call('select_user', 999999)
        ->assertSet('notification_user_id', null)
        ->assertSet('select_user_name', null);
});

it('send_new_notification rechaza al usuario actual aunque la propiedad se asigne directamente', function () {
    Queue::fake();
    $sender = new_user();

    Livewire::actingAs($sender)
        ->test(SidebarNotification::class)
        ->set('notification_user_id', $sender->id)
        ->set('response_type_id', 'info')
        ->set('response_message', 'Hola')
        ->call('send_new_notification')
        ->assertHasErrors(['notification_user_id' => 'not_in']);

    Queue::assertNotPushed(SendNotificationToUserJob::class);
});
