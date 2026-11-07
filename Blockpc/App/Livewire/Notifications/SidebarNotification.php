<?php

declare(strict_types=1);

namespace Blockpc\App\Livewire\Notifications;

use App\Models\User;
use Blockpc\App\Jobs\SendNotificationToUserJob;
use Blockpc\App\Traits\AlertBrowserEvent;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class SidebarNotification extends Component
{
    use AlertBrowserEvent;
    use SelectTwoOnlyOneUserTrait;

    public $sidebar = false;

    public $show_response = false;

    public $new_notification = false;

    public $sku;

    #[Locked]
    public $notification_response;

    #[Locked]
    public $user_to_response;

    public $notification_user_id;

    public $response_type_id;

    public $response_message;

    protected $listeners = [
        'refresh',
        'show_sidebar',
    ];

    public function mount()
    {
        $this->sku = current_user()->id;
    }

    public function render()
    {
        return view('blockpc::livewire.notifications.sidebar', [
            'notifications' => $this->getNotifications(),
        ]);
    }

    public function mark_as_read(string $uuid)
    {
        current_user()
            ->unreadNotifications
            ->where('id', $uuid)
            ->markAsRead();

        $this->dispatch('refresh')->self();
    }

    public function show_sidebar()
    {
        $this->sidebar = true;
    }

    public function hide_sidebar()
    {
        $this->sidebar = false;
        $this->dispatch('close_sidebar')->to(ButtonShowNotifications::class);
    }

    #[Computed()]
    public function types()
    {
        return [
            'info' => 'Informativo (Azul)',
            'warning' => 'Atención (Amarillo)',
            'danger' => 'Urgencia (Rojo)',
            'success' => 'Confirmación (Verde)',
        ];
    }

    public function open_response(string $uuid)
    {
        $this->show_response = true;
        $this->reset('notification_response', 'user_to_response', 'response_type_id', 'response_message');

        $this->notification_response = current_user()
            ->unreadNotifications
            ->where('id', $uuid)
            ->first();

        $this->user_to_response = User::find($this->notification_response->data['from_id']);
        $this->response_type_id = $this->notification_response->data['type'];
    }

    public function send_response()
    {
        $this->validate([
            'response_message' => 'required|string',
            'response_type_id' => 'required|in:info,warning,danger,success',
        ], [
            'response_type_id.required' => 'El tipo de respuesta es obligatorio.',
            'response_type_id.exists' => 'El tipo de respuesta seleccionado no existe.',
            'response_message.required' => 'El mensaje de la notificación es obligatorio.',
            'response_message.string' => 'El mensaje de la notificación debe ser un texto.',
            'response_message.max' => 'El mensaje de la notificación no puede exceder los 255 caracteres.',
        ]);

        // Despachar el trabajo para enviar la notificacion al usuario
        SendNotificationToUserJob::dispatch($this->response_message, $this->response_type_id, $this->user_to_response->id, current_user()->id);

        $this->alert('Respuesta Enviada a '.$this->user_to_response->fullname, 'success', 'Respuesta Enviada');
        $this->show_response = false;
    }

    public function close_response()
    {
        $this->reset('notification_response', 'user_to_response', 'response_type_id', 'response_message', 'show_response');
    }

    public function send_new_notification()
    {
        $this->validate([
            'response_type_id' => 'required|in:info,warning,danger,success',
            'notification_user_id' => ['required', 'exists:users,id', Rule::notIn([current_user()->id])],
            'response_message' => 'required|string|max:255',
        ], [
            'response_type_id.required' => 'El tipo de respuesta es obligatorio.',
            'response_type_id.exists' => 'El tipo de respuesta seleccionado no existe.',
            'notification_user_id.required' => 'El usuario destinatario es obligatorio.',
            'notification_user_id.exists' => 'El usuario seleccionado no existe.',
            'notification_user_id.not_in' => 'No puedes enviarte una notificación a ti mismo.',
            'response_message.required' => 'El mensaje de la notificación es obligatorio.',
            'response_message.string' => 'El mensaje de la notificación debe ser un texto.',
            'response_message.max' => 'El mensaje de la notificación no puede exceder los 255 caracteres.',
        ]);

        $user = User::find($this->notification_user_id);

        // Despachar el trabajo para enviar la notificacion al usuario
        SendNotificationToUserJob::dispatch($this->response_message, $this->response_type_id, $this->notification_user_id, current_user()->id);

        $this->alert('Notificación Enviada a '.$user->fullname, 'success', 'Notificación Enviada');
        $this->reset('notification_user_id', 'select_user_id', 'select_user_name', 'search_user', 'response_type_id', 'response_message', 'new_notification');
    }

    public function open_new_notification()
    {
        $this->clearValidation();
        $this->reset('notification_user_id', 'select_user_id', 'select_user_name', 'search_user', 'response_type_id', 'response_message');
        $this->new_notification = true;
    }

    public function close_new_notification()
    {
        $this->reset('notification_user_id', 'select_user_id', 'select_user_name', 'search_user', 'response_type_id', 'response_message', 'new_notification');
    }

    public function select_user(int|string $user_id): void
    {
        $user = User::where('id', '!=', current_user()->id)->find($user_id);

        if (! $user) {
            $this->reset('notification_user_id', 'select_user_id', 'select_user_name');

            return;
        }

        $this->notification_user_id = $user->id;
        $this->select_user_id = $user->id;
        $this->select_user_name = $user->fullname;
    }

    private function getNotifications()
    {
        $notifications = current_user()->unreadNotifications->map(function ($notification) {
            return (object) [
                'id' => $notification->id,
                'type' => $notification->data['type'],
                'message' => $notification->data['message'],
                'from' => User::find($notification->data['from_id'])->fullname,
                'date' => formato($notification->created_at, 'd/m/Y H:i'),
            ];
        });

        return $notifications;
    }
}
