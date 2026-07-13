<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
requireLogin();

if (!verifyCsrf()) jsonResponse(false, 'Invalid security token.');

$pdo = db();
ensureAiSuggestionsTable($pdo);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'generate':
        generateInsights($pdo);
        break;

    case 'list':
        $rows = $pdo->prepare("SELECT id, title, detail, created_at FROM ai_suggestions WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
        $rows->execute([currentUser()['id']]);
        jsonResponse(true, '', $rows->fetchAll());
        break;

    default:
        jsonResponse(false, 'Unknown action.');
}

function generateInsights(PDO $pdo): void {
    if (!ANTHROPIC_API_KEY) {
        jsonResponse(false, 'AI ফিচার এখনো কনফিগার করা হয়নি। সার্ভারে ANTHROPIC_API_KEY environment variable সেট করুন।');
    }

    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        jsonResponse(false, 'AI SDK ইনস্টল করা নেই। প্রজেক্ট ফোল্ডারে "composer install" চালান।');
    }
    require_once $autoloadPath;

    // ── Summarize the user's current financial state (no raw PII beyond amounts) ──
    $accounts = $pdo->query("SELECT name, type, balance FROM accounts WHERE status = 1 ORDER BY balance DESC")->fetchAll();

    $investments = $pdo->query("
        SELECT status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total_amount,
               COALESCE(SUM(expected_profit),0) AS total_expected_profit
        FROM investments GROUP BY status
    ")->fetchAll();

    $overdue = (int) $pdo->query("SELECT COUNT(*) FROM investments WHERE status='active' AND maturity_date < CURDATE()")->fetchColumn();

    $recentTx = $pdo->query("
        SELECT type, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
        FROM transactions
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY type
    ")->fetchAll();

    $summary = [
        'accounts'            => $accounts,
        'investments_by_status' => $investments,
        'overdue_investment_count' => $overdue,
        'transactions_last_90_days' => $recentTx,
    ];

    $prompt = "তুমি একজন ব্যক্তিগত ফিন্যান্স উপদেষ্টা। নিচের JSON ডেটা হলো একজন ব্যবহারকারীর অ্যাকাউন্ট, বিনিয়োগ ও লেনদেনের সারাংশ (টাকার অঙ্ক ৳ বাংলাদেশি টাকায়):\n\n"
        . json_encode($summary, JSON_UNESCAPED_UNICODE)
        . "\n\nএই ডেটা বিশ্লেষণ করে ৩ থেকে ৫টা সংক্ষিপ্ত, নির্দিষ্ট, কার্যকর পরামর্শ দাও (যেমন: idle টাকা কোথায় বিনিয়োগ করা যায়, overdue বিনিয়োগ নিয়ে কী করা উচিত, অ্যাকাউন্ট ব্যালেন্স বৈচিত্র্য নিয়ে মতামত)। প্রতিটা পরামর্শ বাংলায় লিখবে, সংখ্যা/পরিসংখ্যান উল্লেখ করবে যেখানে প্রাসঙ্গিক।";

    try {
        $client = new \Anthropic\Client(apiKey: ANTHROPIC_API_KEY);

        $message = $client->messages->create(
            model: 'claude-opus-4-8',
            maxTokens: 2048,
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ],
            outputConfig: [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'suggestions' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title'  => ['type' => 'string'],
                                        'detail' => ['type' => 'string'],
                                    ],
                                    'required' => ['title', 'detail'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['suggestions'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        );

        $raw = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $raw = $block->text;
                break;
            }
        }

        $parsed = json_decode($raw, true);
        $suggestions = $parsed['suggestions'] ?? [];

        if (!$suggestions) {
            jsonResponse(false, 'AI কোনো পরামর্শ দিতে পারেনি, আবার চেষ্টা করো।');
        }

        $userId = currentUser()['id'];
        $insert = $pdo->prepare("INSERT INTO ai_suggestions (user_id, title, detail) VALUES (?, ?, ?)");
        foreach ($suggestions as $s) {
            $insert->execute([$userId, $s['title'] ?? '', $s['detail'] ?? '']);
        }

        jsonResponse(true, 'নতুন পরামর্শ তৈরি হয়েছে।', $suggestions);

    } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
        jsonResponse(false, 'AI সার্ভিসে সমস্যা হয়েছে: ' . $e->getMessage());
    }
}
