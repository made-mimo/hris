<?php

use App\Models\CandidateApplication;
use App\Services\SignatureService;
use Livewire\Component;

new class extends Component
{
    public CandidateApplication $application;

    public string $signatureData = '';

    public bool $signed = false;

    public function mount(CandidateApplication $application, SignatureService $signatures): void
    {
        $this->application = $application;
        $this->signed = $signatures->signaturesFor($application, 'recruitment_offer_letter')->isNotEmpty();
    }

    public function sign(SignatureService $signatures): void
    {
        abort_unless($this->application->status === 'job_offered', 404);

        $this->validate(['signatureData' => ['required', 'string', 'starts_with:data:image/']]);

        $signatures->sign(
            signable: $this->application,
            signer: null,
            purpose: 'recruitment_offer_letter',
            content: $this->application->offerLetterContent(),
            method: 'drawn',
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            drawnImageBase64: $this->signatureData,
            externalSignerName: $this->application->candidate->fullName(),
            externalSignerEmail: $this->application->candidate->email,
        );

        $this->signed = true;
    }
};
?>

<div>
    @if($signed)
        <div class="rounded-md border border-border bg-surface p-6 shadow-sm">
            <div class="text-sm text-accent" style="font-weight:600;">You've signed and accepted this offer. Congratulations — HR will be in touch with next steps.</div>
        </div>
    @elseif($application->status !== 'job_offered')
        <div class="rounded-md border border-border bg-surface p-6 shadow-sm text-sm text-text-muted">This offer link is no longer active.</div>
    @else
        <div class="rounded-md border border-border bg-surface p-6 shadow-sm" style="margin-bottom:20px;line-height:1.7;">
            <p>Dear {{ $application->candidate->fullName() }},</p>
            <p>We are pleased to offer you the position of <strong>{{ $application->vacancy->title }}</strong> at Systems Intelligenz Ltd. Please sign below to accept this offer.</p>
        </div>

        <div x-data="{
                drawing: false,
                ctx: null,
                init() {
                    this.ctx = this.$refs.pad.getContext('2d');
                    this.ctx.lineWidth = 2;
                    this.ctx.lineCap = 'round';
                    this.ctx.strokeStyle = '#14151A';
                },
                pos(e) {
                    const rect = this.$refs.pad.getBoundingClientRect();
                    const point = e.touches ? e.touches[0] : e;
                    return [point.clientX - rect.left, point.clientY - rect.top];
                },
                start(e) { this.drawing = true; const [x, y] = this.pos(e); this.ctx.beginPath(); this.ctx.moveTo(x, y); },
                move(e) { if (!this.drawing) return; const [x, y] = this.pos(e); this.ctx.lineTo(x, y); this.ctx.stroke(); },
                stop() { if (!this.drawing) return; this.drawing = false; $wire.set('signatureData', this.$refs.pad.toDataURL('image/png')); },
                clear() { this.ctx.clearRect(0, 0, this.$refs.pad.width, this.$refs.pad.height); $wire.set('signatureData', ''); }
            }"
            class="rounded-md border border-border bg-surface p-5 shadow-sm">
            <label class="mb-1.5 block text-xs font-semibold text-text">Draw your signature below</label>
            <canvas x-ref="pad" width="500" height="150"
                @mousedown="start" @mousemove="move" @mouseup="stop" @mouseleave="stop"
                @touchstart.prevent="start" @touchmove.prevent="move" @touchend.prevent="stop"
                style="width:100%;max-width:500px;height:150px;border:1px dashed var(--color-border);border-radius:8px;background:#fff;touch-action:none;"></canvas>
            <div class="mt-2 flex gap-2">
                <button type="button" @click="clear" class="text-xs font-semibold text-text-muted">Clear</button>
            </div>
            @error('signatureData') <div class="mt-1 text-xs text-danger">Please draw your signature before submitting.</div> @enderror
            <button wire:click="sign" class="mt-3 rounded-sm bg-primary px-4.5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Sign &amp; accept offer</button>
        </div>
    @endif
</div>
