<?php
// ==========================================
// 1. シミュレーション・ロジック（コアデータ構造）
// ==========================================
abstract class MarketEvent {
    public string $name; public string $description; public string $color; public string $icon;
    public function __construct($name, $description, $color, $icon) {
        $this->name = $name; $this->description = $description; $this->color = $color; $this->icon = $icon;
    }
    public function getTrafficModifier(): float { return 1.0; }
    public function getCostModifier(): float { return 1.0; }
}
class SunnyDay extends MarketEvent {
    public function __construct() { parent::__construct("快晴", "絶好のお出かけ日和。客足が20%増加します。", "bg-amber-100 text-amber-800 border-amber-300", "fa-sun"); }
    public function getTrafficModifier(): float { return 1.2; }
}
class HeavyRain extends MarketEvent {
    public function __construct() { parent::__construct("豪雨", "客足が50%に激減。ただし特定時間帯に注文が集中します。", "bg-blue-100 text-blue-800 border-blue-300", "fa-cloud-showers-heavy"); }
    public function getTrafficModifier(): float { return 0.5; }
}
class MeatCrisis extends MarketEvent {
    public function __construct() { parent::__construct("牛肉価格高騰", "サプライチェーンの乱れにより、肉類の原価が50%上昇。", "bg-rose-100 text-rose-800 border-rose-300", "fa-cow"); }
    public function getCostModifier(): float { return 1.5; }
}

class Ingredient {
    public string $name; public int $baseBuyPrice; public int $ttl; public bool $isMeat;
    public function __construct($name, $price, $ttl, $isMeat = false) { $this->name = $name; $this->baseBuyPrice = $price; $this->ttl = $ttl; $this->isMeat = $isMeat; }
}
class StockItem {
    public Ingredient $ingredient; public int $remainingTtl; public int $actualCost;
    public function __construct($ing, $costModifier) { $this->ingredient = $ing; $this->remainingTtl = $ing->ttl; $this->actualCost = (int)($ing->baseBuyPrice * ($ing->isMeat ? $costModifier : 1.0)); }
}
class Inventory {
    private array $stocks = [];
    public function purchase($ing, $qty, $costModifier) {
        $cost = 0; for ($i = 0; $i < $qty; $i++) { $item = new StockItem($ing, $costModifier); $this->stocks[$ing->name][] = $item; $cost += $item->actualCost; } return $cost;
    }
    public function hasIngredients($recipe): bool {
        foreach ($recipe as $name => $qty) { if (!isset($this->stocks[$name]) || count($this->stocks[$name]) < $qty) return false; } return true;
    }
    public function consume($recipe): void {
        foreach ($recipe as $name => $qty) { usort($this->stocks[$name], fn($a, $b) => $a->remainingTtl <=> $b->remainingTtl); for ($i = 0; $i < $qty; $i++) array_shift($this->stocks[$name]); }
    }
    public function processEndOfDayLoss(): int {
        $loss = 0; foreach ($this->stocks as $name => &$items) { $survived = []; foreach ($items as $item) { $item->remainingTtl--; if ($item->remainingTtl <= 0) $loss += $item->actualCost; else $survived[] = $item; } $items = $survived; } return $loss;
    }
    public function getCounts(): array {
        $res = []; foreach ($this->stocks as $name => $items) { if (count($items) > 0) $res[$name] = count($items); } return $res;
    }
}
class CookingTask { public string $menuName; public int $remainingTicks; public int $waitingTicks = 0; public function __construct($name, $ticks) { $this->menuName = $name; $this->remainingTicks = $ticks; } }
class Staff {
    public string $name; public ?CookingTask $task = null; public function __construct($name) { $this->name = $name; }
    public function isIdle(): bool { return $this->task === null; }
    public function tick(): ?CookingTask { if ($this->isIdle()) return null; $this->task->remainingTicks--; if ($this->task->remainingTicks <= 0) { $done = $this->task; $this->task = null; return $done; } return null; }
}
class MenuItem { public string $name; public int $price; public array $recipe; public int $cookingTime; public function __construct($name, $price, $recipe, $time) { $this->name = $name; $this->price = $price; $this->recipe = $recipe; $this->cookingTime = $time; } }

// ==========================================
// 2. データの事前初期化とシミュレーション実行
// ==========================================
$ingBeef  = new Ingredient("特選和牛スライス", 400, 2, true);
$ingOnion = new Ingredient("淡路島産玉ねぎ", 50, 5);
$ingRice  = new Ingredient("国産あきたこまち", 40, 15);

$menu1 = new MenuItem("特製ビーフ丼", 1200, ["特選和牛スライス" => 1, "淡路島産玉ねぎ" => 1, "国産あきたこまち" => 1], 4);
$menu2 = new MenuItem("大盛りつゆだく牛丼", 1400, ["特選和牛スライス" => 1, "淡路島産玉ねぎ" => 2, "国産あきたこまち" => 1], 5);

$daysMeta = [
    1 => ["events" => [new SunnyDay()], "title" => "Day 1: 平穏なランチ営業"],
    2 => ["events" => [new HeavyRain()], "title" => "Day 2: 豪雨のスクラムラッシュ"],
    3 => ["events" => [new HeavyRain(), new MeatCrisis()], "title" => "Day 3: 複合インフレ局面"]
];

$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : 3;
if (!array_key_exists($selectedDay, $daysMeta)) $selectedDay = 3;

$inventory = new Inventory();
$staffs = [new Staff("シェフA（メイン）"), new Staff("シェフB（サポート）")];
$market = $daysMeta[$selectedDay]["events"];

$trafficMultiplier = array_reduce($market, fn($carry, $e) => $carry * $e->getTrafficModifier(), 1.0);
$costMultiplier = array_reduce($market, fn($carry, $e) => $carry * $e->getCostModifier(), 1.0);

$funds = 150000;
if ($selectedDay == 2) $funds = 152380;
if ($selectedDay == 3) $funds = 158950;

$targetStock = (int)(15 * $trafficMultiplier);
$purchaseCost = 0;
$purchaseCost += $inventory->purchase($ingBeef, $targetStock, $costMultiplier);
$purchaseCost += $inventory->purchase($ingOnion, $targetStock, $costMultiplier);
$purchaseCost += $inventory->purchase($ingRice, $targetStock + 5, $costMultiplier);
$funds -= $purchaseCost;

$timelineLogs = [];
$taskQueue = [];
$dailyRevenue = 0;
$servedCount = 0;
$missedCount = 0;
$totalQoS = 0;

for ($tick = 0; $tick < 30; $tick++) {
    $timeStr = date("11:i", strtotime("+$tick minutes", strtotime("11:30")));
    $arrivalRate = 0.3 * $trafficMultiplier;
    if ($trafficMultiplier < 1.0 && $tick >= 15 && $tick <= 25) $arrivalRate = 0.8;

    if (rand(0, 100) / 100 < $arrivalRate) {
        $orderedItem = rand(0, 1) == 0 ? $menu1 : $menu2;
        if ($inventory->hasIngredients($orderedItem->recipe)) {
            $inventory->consume($orderedItem->recipe);
            $taskQueue[] = new CookingTask($orderedItem->name, $orderedItem->cookingTime);
            $dailyRevenue += $orderedItem->price;
            $timelineLogs[] = ["time" => $timeStr, "type" => "order", "msg" => "📥 注文: 【{$orderedItem->name}】が入りました。"];
        } else {
            $missedCount++;
            $timelineLogs[] = ["time" => $timeStr, "type" => "error", "msg" => "❌ 在庫切れ: 【{$orderedItem->name}】食材不足のため注文を拒絶（機会損失）"];
        }
    }

    foreach ($taskQueue as $t) $t->waitingTicks++;
    foreach ($staffs as $staff) { if ($staff->isIdle() && !empty($taskQueue)) $staff->task = array_shift($taskQueue); }
    foreach ($staffs as $staff) {
        $doneTask = $staff->tick();
        if ($doneTask) {
            $qos = max(10, 100 - ($doneTask->waitingTicks * 4));
            $totalQoS += $qos; $servedCount++;
            $timelineLogs[] = ["time" => $timeStr, "type" => "success", "msg" => "✨ 提供完了: 【{$doneTask->menuName}】 by {$staff->name} (待ち: {$doneTask->waitingTicks}分 | QoS: {$qos}点)"];
        }
    }
}

$dailyLoss = $inventory->processEndOfDayLoss();
$funds += $dailyRevenue;
$funds -= $dailyLoss;
$avgQoS = $servedCount > 0 ? round($totalQoS / $servedCount, 1) : 100;
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ロジスティクス・シミュレーター GUI</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans">

    <div class="max-w-7xl mx-auto p-6">
        <header class="flex justify-between items-center border-b border-slate-700 pb-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-emerald-400"><i class="fa-solid fa-chart-line mr-2"></i>構造化ロジスティクス・ダイニング</h1>
                <p class="text-sm text-slate-400">PHPオブジェクト指向 経営シミュレータ・カーネル v2.0</p>
            </div>
            <div class="flex space-x-2 bg-slate-800 p-1 rounded-lg border border-slate-700">
                <?php foreach ($daysMeta as $d => $meta): ?>
                    <a href="?day=<?= $d ?>" class="px-4 py-2 rounded-md text-sm font-medium transition <?= $selectedDay == $d ? 'bg-emerald-500 text-white shadow' : 'text-slate-400 hover:text-slate-200' ?>">
                        Day <?= $d ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 flex items-center space-x-4 shadow-md">
                <div class="p-4 bg-emerald-500/10 rounded-lg text-emerald-400 text-2xl"><i class="fa-solid fa-wallet"></i></div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">現在純資金</p>
                    <p class="text-2xl font-bold">¥<?= number_format($funds) ?></p>
                </div>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 flex items-center space-x-4 shadow-md">
                <div class="p-4 bg-blue-500/10 rounded-lg text-blue-400 text-2xl"><i class="fa-solid fa-users"></i></div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">顧客満足度 (QoS)</p>
                    <p class="text-2xl font-bold"><?= $avgQoS ?> <span class="text-sm font-normal text-slate-400">/ 100 点</span></p>
                </div>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 flex items-center space-x-4 shadow-md">
                <div class="p-4 bg-indigo-500/10 rounded-lg text-indigo-400 text-2xl"><i class="fa-solid fa-kitchen-set"></i></div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">厨房ステータス</p>
                    <div class="flex space-x-2 mt-1">
                        <span class="text-xs bg-slate-700 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded">シェフA: アイドル</span>
                        <span class="text-xs bg-slate-700 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded">シェフB: アイドル</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 shadow-sm">
                    <h2 class="text-lg font-semibold mb-3 text-slate-200 border-b border-slate-700 pb-2"><i class="fa-solid fa-cloud-sun mr-2 text-indigo-400"></i>朝の市場環境</h2>
                    <div class="space-y-3">
                        <?php foreach ($market as $e): ?>
                            <div class="border p-3 rounded-lg <?= $e->color ?>">
                                <div class="font-bold text-sm flex items-center"><i class="fa-solid <?= $e->icon ?> mr-2"></i><?= $e->name ?></div>
                                <div class="text-xs mt-1 opacity-90"><?= $e->description ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 shadow-sm">
                    <h2 class="text-lg font-semibold mb-3 text-slate-200 border-b border-slate-700 pb-2"><i class="fa-solid fa-boxes-stacked mr-2 text-amber-400"></i>冷蔵庫の在庫状況</h2>
                    <p class="text-xs text-slate-400 mb-3">朝の仕入れ投資額: <span class="text-rose-400 font-semibold">¥<?= number_format($purchaseCost) ?></span></p>
                    <div class="divide-y divide-slate-700">
                        <?php foreach ($inventory->getCounts() as $name => $count): ?>
                            <div class="py-2.5 flex justify-between items-center">
                                <span class="text-sm font-medium text-slate-300"><?= $name ?></span>
                                <span class="px-2.5 py-0.5 bg-slate-700 border border-slate-600 rounded-full text-xs font-bold text-slate-200"><?= $count ?> 個</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($inventory->getCounts())): ?>
                            <p class="text-sm text-slate-500 py-2">在庫がありません</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 shadow-sm">
                    <h2 class="text-lg font-semibold mb-4 text-slate-200 border-b border-slate-700 pb-2"><i class="fa-solid fa-clock-history mr-2 text-sky-400"></i>営業ライブ・タイムライン (11:30 〜 12:00)</h2>
                    <div class="max-h-96 overflow-y-auto space-y-2 pr-2">
                        <?php foreach ($timelineLogs as $log): ?>
                            <?php 
                                $badgeColor = "bg-slate-700 text-slate-300";
                                if ($log['type'] === 'success') $badgeColor = "bg-emerald-500/10 text-emerald-400 border border-emerald-500/20";
                                if ($log['type'] === 'error') $badgeColor = "bg-rose-500/10 text-rose-400 border border-rose-500/20";
                            ?>
                            <div class="flex items-start space-x-3 p-2 rounded-lg text-sm transition hover:bg-slate-700/30">
                                <span class="text-xs bg-slate-900 border border-slate-700 text-slate-400 px-2 py-0.5 rounded font-mono mt-0.5"><?= $log['time'] ?></span>
                                <div class="flex-1 px-3 py-1.5 rounded-md text-xs md:text-sm <?= $badgeColor ?>">
                                    <?= $log['msg'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 shadow-sm">
                    <h2 class="text-lg font-semibold mb-4 text-slate-200 border-b border-slate-700 pb-2"><i class="fa-solid fa-receipt mr-2 text-emerald-400"></i>本日の財務・経営リザルト</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700">
                            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-2">💰 財務メトリクス</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span>売上高</span><span class="text-emerald-400 font-semibold">+¥<?= number_format($dailyRevenue) ?></span></div>
                                <div class="flex justify-between"><span>廃棄損失</span><span class="text-rose-400 font-semibold">-¥<?= number_format($dailyLoss) ?></span></div>
                                <div class="border-t border-slate-700 my-2 pt-2 flex justify-between font-bold text-base">
                                    <span>本日収支</span>
                                    <span class="<?= ($dailyRevenue - $dailyLoss) >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                                        <?= ($dailyRevenue - $dailyLoss) >= 0 ? '+' : '' ?>¥<?= number_format($dailyRevenue - $dailyLoss) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700">
                            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-2">📈 オペレーションメトリクス</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span>提供完了</span><span class="font-bold text-slate-200"><?= $servedCount ?> 件</span></div>
                                <div class="flex justify-between"><span>注文拒絶 (機会損失)</span><span class="font-bold text-rose-400"><?= $missedCount ?> 件</span></div>
                                <div class="flex justify-between"><span>平均顧客満足度</span><span class="font-bold text-blue-400"><?= $avgQoS ?> / 100 点</span></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
