# Support Response Acceleration Blueprint

Deliver consistent, context-aware ticket responses that learn from every resolved conversation.

## Fit Checklist
- You triage tickets through a shared inbox (Zendesk, Help Scout, Freshdesk) with exportable transcripts.
- SLA pressure demands quicker first responses without sacrificing empathy.
- Agents already tag tickets or associate them with product areas.

## Data Ingestion
- Batch export transcripts into `support_messages` table with `case_id`, `role`, `body`, `labels`, `resolved_at`.
- Preserve domain language by redacting only sensitive data (PII) and keeping product shorthand intact.

```php
SupportMessage::upsert(
    collect($zendeskTickets)->flatMap(fn ($ticket) => $ticket->messages)->map(fn ($message) => [
        'external_id' => $message->id,
        'case_id' => $message->ticket_id,
        'role' => $message->author_role,
        'body' => Str::squish($message->body),
        'labels' => $ticket->tags,
        'resolved_at' => $ticket->resolved_at,
        'created_at' => $message->created_at,
    ])->all(),
    ['external_id'],
    ['body', 'labels']
);
```

## Sequence Training
- Feed only agent responses to maintain tone and recommended phrasing.
- Cache per product area (e.g., `support:billing`) for targeted suggestions.

```php
$responses = SupportMessage::query()
    ->where('role', 'agent')
    ->whereJsonContains('labels', 'billing')
    ->orderBy('created_at')
    ->pluck('body');

Markovable::sequence('support-sequence')
    ->train($responses)
    ->cache('support:billing');
```

## Predictive Reply Service
- Offer suggested replies via API for agent UI integration.

```php
class SuggestedReplyController
{
    public function __invoke(Request $request): JsonResponse
    {
        $seed = $request->input('summary')." | mood:".$request->input('sentiment', 'neutral');

        $suggestions = Markovable::cache('support:'.$request->segment)
            ->generate($request->input('length', 80), [
                'seed' => $seed,
                'temperature' => 0.4,
            ]);

        return response()->json([
            'ticket' => $request->ticket,
            'suggestions' => $suggestions,
        ]);
    }
}
```

- Provide quick actions in help desk UI to accept, edit, or discard suggestions; capture chosen option for retraining.

## Automation via Jobs
- Attach `AutoTrainObserver` to `SupportMessage` to queue `AnalyzePatternsJob` when new resolutions land.

```php
SupportMessage::created(function (SupportMessage $message) {
    if ($message->role !== 'agent' || !$message->case->isResolved()) {
        return;
    }

    AnalyzePatternsJob::dispatch('support-sequence',[
        'cacheKey' => 'support:'.$message->case->primaryLabel(),
        'payload' => $message->case->agentResponses(),
    ]);
});
```

## Quality Assurance
- Use `SupportResponseTest` to ensure critical phrases remain accessible.

```php
$this->markovableCache('support:billing')
    ->assertPredicts('double-charge', fn ($predictions) =>
        $predictions->contains(fn ($item) => str_contains($item->token, 'refund timeline'))
    );
```

- Monitor `PredictionMade` events to audit usage and track acceptance rate per segment.

## Implementation Tips
- Tokenize by sentences when responses should resemble email paragraphs; fall back to word-level for chat replies.
- Blend hints from `TextGenerator` for empathetic openers and `SequenceGenerator` for procedural steps.
- Determine eligibility rules (escalated cases bypass automation).

## Inspiration
- Roadmap digests: auto-suggest product updates relevant to the ticket topic.
- Post-resolution automation: trigger follow-up survey copy aligned with resolved issue.
- Internal knowledge base: populate canned responses library with the highest-performing suggestions.
