<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$kullanici_adi = $_SESSION['username'];
$user_id = (int)$_SESSION['user_id'];

require_once __DIR__ . '/includes/db.php';

$st = $pdo->prepare("SELECT focus_minutes, short_break_minutes, long_break_minutes, cycles_before_long_break 
                     FROM pomodoro_settings WHERE user_id = ?");
$st->execute([$user_id]);
$settings = $st->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $settings = [
        'focus_minutes' => 25,
        'short_break_minutes' => 5,
        'long_break_minutes' => 15,
        'cycles_before_long_break' => 4
    ];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pomodoro</title>
    <link href="css/stylesheet.css" rel="stylesheet">
    <style>
        .main { flex: 1; padding: 20px; overflow-y: auto; }
        .pomodoro-wrap{max-width:1000px;margin:24px auto;padding:24px;background:#fff;border:1px solid #eee;border-radius:12px}
        .pomodoro-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .card{padding:20px;border:1px solid #ddd;border-radius:12px;background:#fafafa}
        .card h3{margin-top:0}
        .row{margin-bottom:12px}
        .row label{display:inline-block;width:220px;font-weight:600}
        .row input{padding:6px;border:1px solid #ccc;border-radius:6px}
        .btn{padding:8px 14px;border-radius:8px;border:1px solid #333;background:#333;color:#fff;cursor:pointer}
        .btn.secondary{background:#fff;color:#333}
        .digits{font-size:60px;font-weight:bold}
        .pill{padding:4px 10px;border-radius:999px;color:#fff}
        .tiny{font-size:12px;opacity:.7}


    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main">
    <?php include 'includes/header.php'; ?>

    <div class="pomodoro-wrap">
        <div class="pomodoro-grid">
            <div class="card">
                <h3>Ayarlar</h3>
                <form id="settingsForm">
                    <div class="row">
                        <label>Odak (dk)</label>
                        <input type="number" name="focus_minutes" value="<?= (int)$settings['focus_minutes'] ?>" min="1" max="180">
                    </div>
                    <div class="row">
                        <label>Kısa Mola (dk)</label>
                        <input type="number" name="short_break_minutes" value="<?= (int)$settings['short_break_minutes'] ?>" min="1" max="60">
                    </div>
                    <div class="row">
                        <label>Uzun Mola (dk)</label>
                        <input type="number" name="long_break_minutes" value="<?= (int)$settings['long_break_minutes'] ?>" min="1" max="90">
                    </div>
                    <div class="row">
                        <label>Kaç odaktan sonra uzun mola?</label>
                        <input type="number" name="cycles_before_long_break" value="<?= (int)$settings['cycles_before_long_break'] ?>" min="1" max="12">
                    </div>
                    <button class="btn" type="submit">Kaydet</button>
                </form>
                <p id="saveMsg" class="tiny"></p>
            </div>

            <div class="card">
                <h3>Sayaç</h3>
                <div class="mode"><span id="modePill" class="pill" style="background:#333">FOCUS</span></div>
                <div class="digits" id="digits">00:00</div>
                <div>
                    <button id="btnStart" class="btn">Başlat</button>
                    <button id="btnPause" class="btn secondary" disabled>Duraklat</button>
                    <button id="btnReset" class="btn secondary" disabled>Sıfırla</button>
                </div>
                <div class="tiny">Döngü: <span id="cycleInfo">0</span> / <span id="cycleTarget"><?= (int)$settings['cycles_before_long_break'] ?></span></div>
            </div>
        </div>
    </div>
</div>

<script>
    const USER_ID = <?= $user_id ?>;
    let settings = {
        focus: <?= (int)$settings['focus_minutes'] ?>,
        shortB: <?= (int)$settings['short_break_minutes'] ?>,
        longB: <?= (int)$settings['long_break_minutes'] ?>,
        untilLong: <?= (int)$settings['cycles_before_long_break'] ?>
    };
    let mode='focus', remaining=settings.focus*60, timerId=null, startedAt=null, cycleCount=0;
    const digits=document.getElementById('digits'),modePill=document.getElementById('modePill'),cycleInfo=document.getElementById('cycleInfo'),cycleTarget=document.getElementById('cycleTarget');
    const btnStart=document.getElementById('btnStart'),btnPause=document.getElementById('btnPause'),btnReset=document.getElementById('btnReset');

    function fmt(s){return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');}
    function render(){digits.textContent=fmt(remaining);modePill.textContent=mode==='focus'?'FOCUS':(mode==='short_break'?'KISA MOLA':'UZUN MOLA');cycleInfo.textContent=cycleCount;}
    function startTimer(){if(timerId)return;startedAt=new Date();btnStart.disabled=true;btnPause.disabled=false;btnReset.disabled=false;timerId=setInterval(()=>{remaining--;render();if(remaining<=0){clearInterval(timerId);timerId=null;finish();}},1000);}
    function pauseTimer(){clearInterval(timerId);timerId=null;btnStart.disabled=false;btnPause.disabled=true;}
    function resetTimer(aborted=true){if(timerId){clearInterval(timerId);timerId=null;}if(startedAt&&aborted)logSession('aborted');remaining=(mode==='focus'?settings.focus:(mode==='short_break'?settings.shortB:settings.longB))*60;btnStart.disabled=false;btnPause.disabled=true;btnReset.disabled=true;startedAt=null;render();}
    async function finish(){
        await logSession('completed');

        await chime();

        await sleep(2500);

        if(mode === 'focus'){
            cycleCount++;
            if(cycleCount % settings.untilLong === 0){
                mode = 'long_break';
                remaining = settings.longB * 60;
            } else {
                mode = 'short_break';
                remaining = settings.shortB * 60;
            }
        } else {
            mode = 'focus';
            remaining = settings.focus * 60;
        }
        render();
        startTimer();
    }
    async function logSession(status){try{const planned=(mode==='focus'?settings.focus:(mode==='short_break'?settings.shortB:settings.longB));const payload=new URLSearchParams({mode,planned_minutes:planned,started_at:startedAt?startedAt.toISOString():new Date().toISOString(),ended_at:new Date().toISOString(),status});await fetch('pomodoro_log.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:payload.toString()});}catch(e){}finally{startedAt=null;}}
    btnStart.onclick=startTimer;btnPause.onclick=pauseTimer;btnReset.onclick=()=>resetTimer(true);

    document.getElementById('settingsForm').addEventListener('submit',async e=>{
        e.preventDefault();
        const fd=new FormData(e.target);
        const body=new URLSearchParams(fd.entries());
        const res=await fetch('pomodoro_save_settings.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()});
        const j=await res.json();if(j.ok){settings.focus=+fd.get('focus_minutes');settings.shortB=+fd.get('short_break_minutes');settings.longB=+fd.get('long_break_minutes');settings.untilLong=+fd.get('cycles_before_long_break');cycleCount=0;remaining=settings.focus*60;render();document.getElementById('saveMsg').textContent='Ayarlar kaydedildi ✔️';}
    });
    render();
    function sleep(ms){ return new Promise(r => setTimeout(r, ms)); }

    let _audioCtx = null;
    async function chime() {
        try {
            if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const ctx = _audioCtx;

            const sequence = [
                { freq: 880,   dur: 220 },
                { gap: 80 },
                { freq: 1319,  dur: 250 }
            ];
            for (const step of sequence) {
                if (step.gap) { await sleep(step.gap); continue; }
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.value = step.freq;

                const now = ctx.currentTime;
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.2, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + step.dur/1000);

                osc.connect(gain).connect(ctx.destination);
                osc.start(now);
                osc.stop(now + step.dur/1000);

                await sleep(step.dur);
            }
        } catch(e) {
            console.warn('chime error', e);
        }
    }
</script>
</body>
</html>
