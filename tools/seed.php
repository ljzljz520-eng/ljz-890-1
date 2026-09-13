<?php
/**
 * 演示数据播种脚本
 * 用法：php tools/seed.php          （清空并重播种；保留/重建默认管理员）
 *       php tools/seed.php --keep   （仅在库为空时播种）
 */
require_once __DIR__ . '/../functions.php';

$keep = in_array('--keep', $argv, true);
$hasData = (int)$pdo->query('SELECT COUNT(*) FROM elders')->fetchColumn() > 0;
if ($keep && $hasData) {
    echo "数据库已有内容，跳过播种。\n";
    exit(0);
}

echo "播种演示数据…\n";
$pdo->exec('PRAGMA foreign_keys = OFF');
foreach (['photos', 'stories', 'submissions', 'elders'] as $t) {
    $pdo->exec("DELETE FROM {$t}");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='{$t}'");
}
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->prepare('INSERT INTO elders (name,gender,birth_year,death_year,era,hometown,bio) VALUES (?,?,?,?,?,?,?)')
    ->execute(['王秀兰', 'female', 1932, 2021, '三十年代生人', '山东青岛',
        '家中长辈都唤她“秀兰婶”。一生勤俭，做得一手好面食，晚年最爱给孙辈讲老院子里的旧事。']);
$elder1 = (int)$pdo->lastInsertId();

$pdo->prepare('INSERT INTO elders (name,gender,birth_year,death_year,era,hometown,bio) VALUES (?,?,?,?,?,?,?)')
    ->execute(['李建国', 'male', 1955, null, '五十年代生人', '江苏南京',
        '年轻时在东北插队三年，回城后进了机床厂，干到退休。性格开朗，是家里的“故事匣子”。']);
$elder2 = (int)$pdo->lastInsertId();

$stories = [
    [$elder1, '外婆的槐花饼', 1958, '青岛 · 老院子', '五十年代',
        "每到五月，老院那棵老槐树就开满白花。外婆带着我把刚开的槐花捋下来，用井水淘干净，掺一点玉米面贴饼子。\n\n那时候粮食紧，槐花饼是难得的香。外婆总说：“日子苦，可嘴里得有点甜。”饼贴在锅边，烤出一层焦壳，她把最焦的那片夹给我。\n\n如今超市里什么都有，可我再也没吃到过那个味道。",
        'public'],
    [$elder1, '姥爷留下的那封家书', 1976, '青岛', '七十年代',
        "姥爷在外地做工，一年回不了两次家。1976 年秋天他托人捎回一封信，字写得很慢，说惦记家里的煤够不够烧，叮嘱外婆别舍不得吃菜，末尾才写一句“我在这里都好，勿念”。\n\n外婆把信夹在箱底，每年梅雨季过后都要拿出来晒一晒。这封信现在收在我家书柜最上层——家里的事，我们说好只在自家人之间讲。",
        'family'],
    [$elder2, '插队那年的大雪', 1974, '黑龙江 · 生产大队', '七十年代',
        "1974 年冬天雪下得能没到大腿根。我们几个知青挤在一铺炕上，半夜里房东大爷抱来一捆干豆秸，把炕烧得滚烫。\n\n第二天门都推不开，大伙从窗户跳出去铲雪。大爷说：“人哪，跟这豆子一样，压得越实，来年蹦得越高。”这话我记了一辈子。",
        'public'],
    [$elder2, '进厂第一天', 1979, '南京 · 机床厂', '七十年代末',
        "回城后进机床厂报到那天，师傅递给我一把卡尺，说：“饭碗给你了，端不端得稳，看你自己。”\n\n我把那把卡尺用了三十年，退休时徒弟要留，我没舍得。",
        'public'],
];
$ins = $pdo->prepare("INSERT INTO stories (elder_id,title,story_year,location,era,transcript,visibility,status,created_by)
                      VALUES (?,?,?,?,?,?,?,'published',1)");
foreach ($stories as $s) {
    $ins->execute($s);
}

// 一条待审核的亲友投稿
$pdo->prepare("INSERT INTO submissions (elder_id,contributor,contact,relation,title,story_year,location,content,status)
               VALUES (?,?,?,?,?,?,?,?,'pending')")
    ->execute([$elder1, '李小满', '微信 liuxiaoman', '外孙女',
        '外婆讲过的“借粮”往事', 1961, '青岛',
        '记得有一年过年，外婆把仅剩的半袋白面分出一半，让妈妈给隔壁孤寡的赵奶奶送去。外婆说：“咱家难，人家更难。”这件事妈妈也常提起，想请管理员帮忙收录到外婆的故事里。']);

echo "播种完成：\n";
echo '- 长辈 2 位（王秀兰、李建国）', "\n";
echo '- 故事 4 段（其中 1 段为「仅家人可见」示例）', "\n";
echo '- 待审核投稿 1 条（登录后台可体验审核流程）', "\n";
echo '- 默认管理员：admin / family2026（请尽快修改密码）', "\n";
