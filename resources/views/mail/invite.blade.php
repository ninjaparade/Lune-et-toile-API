<x-mail::message>
# {{ $inviter->name }} invited you to play

@if ($hasAccount)
{{ $inviter->name }} (**{{ '@'.$inviter->username }}**) added you as a friend on {{ config('app.name') }}. Open the app to start a game together.
@else
{{ $inviter->name }} (**{{ '@'.$inviter->username }}**) wants to play chess with you on {{ config('app.name') }} — an unhurried game where you each move whenever you like.

Get the app and **create an account with this email address**. You'll be friends with {{ $inviter->name }} straight away and can start a game.
@endif

{{ config('app.name') }}
</x-mail::message>
