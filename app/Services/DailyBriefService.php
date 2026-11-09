<?php

namespace App\Services;

use App\Models\DailyLearningCompletion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DailyBriefService
{
    private const WORDS = [
        ['empathy', 'EM-puh-thee', 'The ability to understand another person’s feelings or point of view.', 'The agent showed empathy by acknowledging the customer’s frustration before offering a solution.', 'understanding'],
        ['concise', 'kuhn-SISE', 'Giving the necessary information clearly and in few words.', 'Keep your call notes concise so the next agent can understand them quickly.', 'brief'],
        ['proactive', 'proh-AK-tiv', 'Taking action before a problem becomes more serious.', 'A proactive follow-up prevented the delivery concern from becoming a complaint.', 'forward-looking'],
        ['rapport', 'ruh-PORE', 'A comfortable and trusting relationship between people.', 'Using the customer’s name helped the representative build rapport.', 'connection'],
        ['clarify', 'KLAIR-uh-fy', 'To make information easier to understand or remove uncertainty.', 'Please clarify which invoice the customer is asking about.', 'explain'],
        ['resilient', 'rih-ZIL-yuhnt', 'Able to recover and continue after difficulty or pressure.', 'The team stayed resilient during a day with unusually high call volume.', 'adaptable'],
        ['escalate', 'ES-kuh-layt', 'To pass an issue to someone with greater authority or expertise.', 'Escalate the billing dispute when it falls outside your approval limit.', 'refer'],
        ['articulate', 'ar-TIK-yuh-layt', 'Able to express ideas clearly and effectively.', 'She gave an articulate explanation of the account changes.', 'eloquent'],
        ['diligent', 'DIL-uh-juhnt', 'Careful and persistent in completing work correctly.', 'A diligent review caught the incorrect email address before submission.', 'thorough'],
        ['reassure', 'ree-uh-SHUR', 'To restore someone’s confidence or reduce worry.', 'Reassure the caller that the case has been assigned and is being monitored.', 'comfort'],
        ['verify', 'VAIR-uh-fy', 'To check that information is accurate or true.', 'Verify the account details before discussing private information.', 'confirm'],
        ['resolution', 'rez-uh-LOO-shuhn', 'The successful solution to a problem or disagreement.', 'The customer accepted the refund as a fair resolution.', 'solution'],
        ['collaborate', 'kuh-LAB-uh-rayt', 'To work with others toward a shared result.', 'Agents collaborate with quality analysts to improve call handling.', 'cooperate'],
        ['prioritize', 'pry-OR-uh-tize', 'To arrange work by importance or urgency.', 'Prioritize the waiting customer before returning to optional paperwork.', 'rank'],
        ['accountable', 'uh-KOWN-tuh-buhl', 'Responsible for actions, decisions, and results.', 'Each employee is accountable for keeping attendance records accurate.', 'responsible'],
    ];

    private const LESSONS = [
        ['Customer Experience', 'Acknowledge, clarify, then solve', 'A strong service response first recognizes the concern, asks focused questions, and only then presents the next step.', 'Which response best demonstrates active listening?', ['Immediately read a standard script', 'Restate the concern and confirm your understanding', 'Place the caller on hold without explanation', 'Promise an outcome you cannot approve'], 1, 'Restating the concern confirms that you heard the customer correctly before acting.'],
        ['Data Privacy', 'Use the minimum necessary information', 'Access and share only the personal information needed to complete the authorized task.', 'What should you do before discussing account details?', ['Verify the customer using the approved process', 'Ask for their social media profile', 'Share the full account history', 'Copy the information into personal notes'], 0, 'Approved identity verification protects the customer and the company.'],
        ['Communication', 'Specific notes create smooth handoffs', 'Useful notes state the issue, actions taken, current status, and next owner without unnecessary personal opinions.', 'Which note is most useful?', ['Customer was difficult', 'Called again', 'Verified order 1042; replacement requested; awaiting supervisor approval', 'Problem handled'], 2, 'The specific note gives the next person the context and status needed to continue.'],
        ['Cybersecurity', 'Unexpected urgency is a warning sign', 'Phishing messages often pressure people to act quickly, reveal credentials, or open unfamiliar links.', 'What is the safest response to an unexpected password-reset email?', ['Click immediately', 'Forward it to everyone', 'Use the official site directly and report the suspicious message', 'Reply with your password'], 2, 'Opening the official site independently avoids the untrusted link and reporting helps protect others.'],
        ['Call Readiness', 'Work always takes priority', 'Idle-time activities should be instantly pausable so calls and customer work receive immediate attention.', 'What should happen when a call arrives during a mini-game?', ['Finish the round first', 'Ignore the notification', 'Pause the game and handle the call', 'Ask another agent to take every call'], 2, 'The call is the work priority, so optional activity must pause immediately.'],
        ['Quality', 'Accuracy comes before assumptions', 'Confirm names, dates, amounts, and requested actions instead of filling gaps with guesses.', 'A customer gives an unclear date. What should you do?', ['Choose the most likely date', 'Ask a clear follow-up question', 'Leave the record blank without explanation', 'End the call'], 1, 'A focused follow-up prevents an avoidable recording or processing error.'],
        ['Teamwork', 'Escalate with context', 'An effective escalation includes what happened, what was verified, what was attempted, and the decision needed.', 'What makes an escalation useful?', ['Only the customer’s name', 'A long emotional description', 'Relevant facts, attempted steps, and the requested decision', 'No notes so the reviewer starts fresh'], 2, 'Complete, relevant context helps the next person act quickly and correctly.'],
    ];

    public function data(?User $user = null): array
    {
        $date = now('Asia/Manila')->startOfDay();
        $word = self::WORDS[$this->dailyIndex($date, count(self::WORDS), 17)];
        $lesson = self::LESSONS[$this->dailyIndex($date, count(self::LESSONS), 31)];
        $completion = $user ? DailyLearningCompletion::query()->where('user_id', $user->id)->whereDate('learning_date', $date)->first() : null;

        return [
            'date' => $date->toDateString(),
            'word' => ['term' => $word[0], 'pronunciation' => $word[1], 'meaning' => $word[2], 'example' => $word[3], 'synonym' => $word[4]],
            'lesson' => ['category' => $lesson[0], 'title' => $lesson[1], 'body' => $lesson[2], 'question' => $lesson[3], 'options' => $lesson[4], 'correct_index' => $lesson[5], 'explanation' => $lesson[6]],
            'news' => $this->news(),
            'progress' => ['completed' => (bool) $completion, 'correct' => $completion?->is_correct, 'streak' => $user ? $this->streak($user) : 0],
        ];
    }

    public function complete(User $user, int $answer): array
    {
        $date = now('Asia/Manila')->startOfDay();
        $lesson = self::LESSONS[$this->dailyIndex($date, count(self::LESSONS), 31)];
        $correct = $answer === $lesson[5];
        $completion = DailyLearningCompletion::query()
            ->where('user_id', $user->id)
            ->whereDate('learning_date', $date)
            ->first() ?? new DailyLearningCompletion;
        $completion->fill([
            'user_id' => $user->id,
            'learning_date' => $date->toDateString(),
            'answer' => $lesson[4][$answer] ?? null,
            'is_correct' => $correct,
        ])->save();

        return ['correct' => $correct, 'correct_index' => $lesson[5], 'explanation' => $lesson[6], 'streak' => $this->streak($user)];
    }

    private function news(): array
    {
        return Cache::remember('daily-brief:news', now()->addMinutes(30), function (): array {
            $feeds = [
                'us' => ['label' => 'United States', 'url' => 'https://news.google.com/rss/search?q=United+States+when:1d&hl=en-US&gl=US&ceid=US:en'],
                'ca' => ['label' => 'Canada', 'url' => 'https://news.google.com/rss/search?q=Canada+when:1d&hl=en-CA&gl=CA&ceid=CA:en'],
            ];
            $result = [];
            foreach ($feeds as $key => $feed) {
                $items = $this->readFeed($feed['url']);
                $result[$key] = ['label' => $feed['label'], 'items' => $items];
            }
            if (collect($result)->pluck('items')->flatten(1)->isNotEmpty()) {
                Cache::forever('daily-brief:news:last', $result);
            }

            return collect($result)->pluck('items')->flatten(1)->isNotEmpty()
                ? $result
                : Cache::get('daily-brief:news:last', $result);
        });
    }

    private function readFeed(string $url): array
    {
        try {
            $body = Http::accept('application/rss+xml')->withUserAgent('DivertexDailyBrief/1.0')->connectTimeout(3)->timeout(7)->get($url)->throw()->body();
            if (! function_exists('simplexml_load_string')) {
                return [];
            }
            $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
            if (! $xml?->channel?->item) {
                return [];
            }

            $items = [];
            foreach ($xml->channel->item as $item) {
                $link = filter_var((string) $item->link, FILTER_VALIDATE_URL) ?: null;
                if ($link) {
                    $items[] = [
                        'title' => trim((string) $item->title),
                        'url' => $link,
                        'published_at' => rescue(fn () => Carbon::parse((string) $item->pubDate)->toISOString(), null, false),
                        'source' => trim((string) ($item->source ?? 'Google News')) ?: 'Google News',
                    ];
                }
                if (count($items) === 5) {
                    break;
                }
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    private function streak(User $user): int
    {
        $dates = DailyLearningCompletion::query()->where('user_id', $user->id)->latest('learning_date')->pluck('learning_date')->map(fn ($date) => Carbon::parse($date)->toDateString())->flip();
        $cursor = now('Asia/Manila')->startOfDay();
        if (! $dates->has($cursor->toDateString())) {
            $cursor = $cursor->subDay();
        }
        $streak = 0;
        while ($dates->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    private function dailyIndex(CarbonInterface $date, int $count, int $salt): int
    {
        return abs((int) crc32($date->toDateString().':'.$salt)) % $count;
    }
}
