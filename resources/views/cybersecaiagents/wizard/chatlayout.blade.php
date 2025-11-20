@extends('template')
@section('main')
<style>
.chat-shell { max-width: 650px; margin: 44px auto 0; background:#fff; min-height:429px;padding:0 0 55px 0; border-radius:23px; box-shadow:0 4px 26px #16d58d15; position:relative;}
.chatlog{list-style:none;padding:38px 22px 10px 22px;margin:0;height:345px;overflow-y:auto;}
.chat-bubble, .user-bubble, .agent-bubble {display:inline-block;max-width:80%;border-radius:19px; font-size:1.1rem;padding:13px 21px;box-shadow:0 2px 10px #10a37f08;border:1px solid #eef7f7; word-break:break-word;}
.agent-bubble {background:#e6fff7; color:#10a37f;}
.user-bubble {background:#10a37f;color:#fff;}
.chat-input {font-family:inherit;font-size:1.13rem;width:70%;border-radius:13px;border:1px solid #acdece;padding:11px 14px;margin-top:17px;}
.chat-next-btn {background:#10a37f;color:#fff;border:none;padding:12px 33px;border-radius:12px;font-weight:700;font-size:1.11rem;margin-top:19px;box-shadow:0 1px 6px #22eec619;transition:background .17s;}
.chat-next-btn:hover{background:#079168;}
.typing-indicator {margin:7px 0 0 11px;}
.typing-dot {width:8px;height:8px;background:#10a37f;display:inline-block;border-radius:50%;margin:0 2px;animation:dot 1.1s infinite both;}
.typing-dot:nth-child(2) {animation-delay:.23s;}
.typing-dot:nth-child(3) {animation-delay:.45s;}
@keyframes dot {0%,80%,100%{transform:scale(1);} 40%{transform:scale(1.25);}}
.chat-select-option {display:block;margin-top:21px;padding:10px;border-radius:9px;background:#f3fffb;}
.chat-option-desc {display:block; color:#1f6d5c99;font-size:.99em;margin-left:9px;}
</style>
<div class="chat-shell">
    @yield('chat-body')
</div>
@endsection