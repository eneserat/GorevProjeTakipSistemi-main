<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
$kullanici_adi = $_SESSION['username'];
$rol = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&display=swap" rel="stylesheet">
    <link href="css/stylesheet.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<style>
    .fc-title-force { padding: 2px 4px; font-weight: 600; }
    .fc .fc-daygrid-event { border-radius: 6px; overflow: hidden; }
    .fc .fc-daygrid-bg-harness { z-index: 1; }
    .fc .fc-daygrid-event-harness { z-index: 2; }
    .fc .fc-event-title { display: block !important; white-space: normal; }
</style>
</head>
<body>
<?php
include 'includes/sidebar.php';
?>
<div class="main">
<?php
include "includes/header.php";
?>
<div class="stats">
    <div class="stat-box">
        <h3>Toplam Proje</h3>
        <p id="statTotalProjects">0</p>
    </div>
    <div class="stat-box">
        <h3>Tamamlanan Proje</h3>
        <p id="statCompletedProjects">0</p> </div>
    <div class="stat-box">
        <h3>Görev Sayısı</h3>
        <p id="statTaskCount">0</p> </div>
    </div>
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Projelerim Takvimi</h5>
            <div id="calendar"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="incompleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bitmemiş Projelerin Var</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p>Aşağıdaki projeler <strong>completed</strong> değil. Çıkış yapmak istediğine emin misin?</p>
                <ul id="incompleteList" class="list-group mb-2"></ul>
                <small class="text-muted d-block">İstersen projeleri tamamlayıp sonra çıkış yap.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-dark" id="confirmLogoutBtn">Yine de Çıkış Yap</button>
            </div>
        </div>
    </div>
</div>
<script src="https://kit.fontawesome.com/a076d05399.js"></script>
<script>
    localStorage.setItem("id", "<?= $_SESSION['user_id'] ?>");
    localStorage.setItem("username", "<?= $_SESSION['username'] ?>");
    localStorage.setItem("role", "<?= $_SESSION['role'] ?>");
    const ctx = document.getElementById('chart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'],
            datasets: [{
                label: 'Aktivite',
                data: [18, 27, 23, 34, 35, 22],
                borderColor: 'cyan',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: 'cyan'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    (function(){
        let hasIncomplete = false;
        let cachedProjects = [];
        let pendingLogoutUrl = null;

        fetch('/api/incomplete_projects.php', { credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(data => {
                hasIncomplete = (data.count || 0) > 0;
                cachedProjects = data.projects || [];
            })
            .catch(()=>{  });

        window.addEventListener('beforeunload', function (e) {
            if (hasIncomplete) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.addEventListener('click', function(e){
            const a = e.target.closest('[data-logout]');
            if (!a) return;

            if (!hasIncomplete) {
                return;
            }

            e.preventDefault();
            pendingLogoutUrl = a.getAttribute('href');

            const list = document.getElementById('incompleteList');
            list.innerHTML = '';
            cachedProjects.slice(0, 8).forEach(p => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.innerHTML = `
<span>${escapeHtml(p.name || ('Proje #' + p.id))}</span>
<span class="badge bg-secondary">${p.status}</span>
`;
                list.appendChild(li);
            });
            if (cachedProjects.length > 8) {
                const more = document.createElement('li');
                more.className = 'list-group-item text-muted';
                more.textContent = `+${cachedProjects.length - 8} tane daha...`;
                list.appendChild(more);
            }

            const modalEl = document.getElementById('incompleteModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        });

        document.getElementById('confirmLogoutBtn')?.addEventListener('click', function(){
            if (pendingLogoutUrl) {
                window.location.href = pendingLogoutUrl;
            }
        });

        function escapeHtml(s){
            return String(s).replace(/[&<>"']/g, m => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[m]));
        }

        function checkAgain(){
            fetch('/api/incomplete_projects.php', { credentials: 'same-origin' })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(data => {
                    hasIncomplete = (data.count || 0) > 0;
                    cachedProjects = data.projects || [];
                }).catch(()=>{});
        }
    })();
</script>
<style>
    #calendar { min-height: 600px; }
    @media (max-width: 768px){ #calendar { min-height: 520px; } }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('calendar');

        const calendar = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            locale: 'tr',
            firstDay: 1,
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            displayEventTime: false,
            eventDisplay: 'block',
            events: 'api/my_projects_calender.php',

            eventContent(arg) {
                if (arg.event.display === 'background') return;
                const t = arg.event.title || '';
                return { html: `<div class="fc-title-force">${t}</div>` };
            },

            eventDidMount(info){
                const tip = info.event.extendedProps?.tooltip;
                if (tip) info.el.setAttribute('title', tip);
                if (info.event.extendedProps?.type === 'label') {
                    info.el.style.zIndex = 3;
                }
            },
            eventOrder: (a, b) => {
                const at = a.extendedProps?.type || '';
                const bt = b.extendedProps?.type || '';
                const rank = { 'projbg': 0, 'taskbg': 0, 'proj': 1, 'task': 2 };
                const ra = rank[at] ?? 3;
                const rb = rank[bt] ?? 3;
                if (ra !== rb) return ra - rb;
                const ta = (a.title || '').localeCompare(b.title || '');
                return ta;
            },
        });

        calendar.render();
    });
</script>
<script>
    (async () => {
        try {
            const res = await fetch('/api/dashboard_stats.php', { credentials: 'include' });
            const d = await res.json();
            if (!d.error) {
                document.getElementById('statTotalProjects').textContent     = d.total_projects ?? 0;
                document.getElementById('statCompletedProjects').textContent = d.completed_projects ?? 0;
                document.getElementById('statTaskCount').textContent         = d.total_tasks ?? 0;
            }
        } catch (err) {
            console.error(err);
        }
    })();
</script>


</body>
</html>
