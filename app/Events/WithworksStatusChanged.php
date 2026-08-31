<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 창고에서 상태가 바뀐 것을 화면에 알린다.
 *
 * 웹훅은 사람이 보고 있지 않을 때 들어온다. 표에만 남기면 담당자가 목록을 새로
 * 불러야 알게 되고, 출고나 취소처럼 곧 손을 써야 하는 일이 늦어진다.
 *
 * 무엇이 바뀌었는지 한 줄로 알린다 — 자세한 것은 그 화면에 가서 본다.
 */
class WithworksStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string  $event,
        public readonly string  $title,
        public readonly string  $body,
        public readonly ?string $url  = null,
        /** info · success · warning · danger — 토스트 색을 가른다 */
        public readonly string  $tone = 'info',
        /**
         * 이 사람에게만 띄운다 — 없으면 관리자 전원에게 띄운다.
         *
         * 판매 쪽 사건은 임자가 따로 없어 팀이 함께 본다. 반품은 접수자가 있고,
         * 그 사람이 다음에 무엇을 할지 정한다 — 전원에게 띄우면 정작 할 일이 있는
         * 사람의 화면에서 남의 건에 섞여 묻힌다(2026-08-31 회신).
         */
        public readonly ?int $userId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel(
            $this->userId ? 'App.Models.User.' . $this->userId : 'admin'
        )];
    }

    public function broadcastAs(): string
    {
        return 'withworks.status';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'body'  => $this->body,
            'url'   => $this->url,
            'tone'  => $this->tone,
        ];
    }
}
