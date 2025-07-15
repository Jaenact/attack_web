<?php
session_start();
if (!isset($_SESSION['admin'])) { echo "<script>alert('관리자만 접근 가능합니다.');location.href='index.php';</script>"; exit(); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>시스템 리소스 상태 모니터링</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
  <style>
    .stat-card{display:inline-block;background:#f5f7fa;border-radius:12px;padding:24px 32px;margin:12px 16px;min-width:220px;box-shadow:0 2px 8px rgba(0,0,0,0.07);text-align:center;}
    .stat-title{font-size:1.1rem;font-weight:700;margin-bottom:8px;}
    .stat-value{font-size:2.1rem;font-weight:800;}
    .stat-unit{font-size:1rem;color:#888;}
    @media(max-width:700px){.stat-card{padding:14px 6px;min-width:120px;}}
  </style>
</head>
<body style="background:linear-gradient(135deg,rgba(0,91,172,0.08) 0%,rgba(0,91,172,0.13) 100%);min-height:100vh;">
  <main style="max-width:900px;margin:40px auto 0 auto;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,91,172,0.10);padding:40px 32px;">
    <h2 style="font-size:2rem;font-weight:700;color:#005BAC;margin-bottom:18px;">시스템 리소스 상태 모니터링</h2>
    <div id="statCards" style="display:flex;flex-wrap:wrap;gap:12px 8px;"></div>
    <div style="margin-top:32px;">
      <canvas id="cpuChart" height="60"></canvas>
      <canvas id="memChart" height="60"></canvas>
      <canvas id="diskChart" height="60"></canvas>
    </div>
    <a href="../index.php" style="display:inline-block;margin-top:24px;padding:10px 28px;background:linear-gradient(90deg,#005BAC 60%,#0076d7 100%);color:#fff;font-weight:700;border-radius:8px;text-decoration:none;box-shadow:0 2px 8px rgba(0,91,172,0.10);transition:background 0.2s;">← 대시보드로</a>
  </main>
  <script>
    let cpuData=[], memData=[], diskData=[], labels=[];
    function getGradient(ctx, color) {
      const gradient = ctx.createLinearGradient(0, 0, 0, 180);
      gradient.addColorStop(0, color+"22");
      gradient.addColorStop(1, color+"00");
      return gradient;
    }
    function fetchStatus() {
      fetch('system_status_api.php').then(r=>r.json()).then(d=>{
        document.getElementById('statCards').innerHTML = `
          <div class='stat-card'><div class='stat-title'>CPU 부하</div><div class='stat-value'>${d.cpu_load.toFixed(2)}</div><div class='stat-unit'>1min load</div></div>
          <div class='stat-card'><div class='stat-title'>메모리 사용</div><div class='stat-value'>${(d.mem_used/1024/1024).toFixed(1)} / ${(d.mem_total/1024/1024).toFixed(1)}</div><div class='stat-unit'>GB 사용 / 전체</div></div>
          <div class='stat-card'><div class='stat-title'>디스크 사용</div><div class='stat-value'>${(d.disk_used/1024/1024/1024).toFixed(1)} / ${(d.disk_total/1024/1024/1024).toFixed(1)}</div><div class='stat-unit'>GB 사용 / 전체</div></div>
          <div class='stat-card'><div class='stat-title'>업타임</div><div class='stat-value'>${d.uptime}</div><div class='stat-unit'>서버 가동 시간</div></div>
          <div class='stat-card'><div class='stat-title'>네트워크</div><div class='stat-value'>${(d.net_rx/1024/1024).toFixed(1)} / ${(d.net_tx/1024/1024).toFixed(1)}</div><div class='stat-unit'>MB 수신 / 송신</div></div>
        `;
        // 그래프 데이터
        if(labels.length>30){labels.shift();cpuData.shift();memData.shift();diskData.shift();}
        labels.push(new Date().toLocaleTimeString().slice(3));
        cpuData.push(d.cpu_load);
        memData.push(d.mem_used/1024/1024);
        diskData.push(d.disk_used/1024/1024/1024);
        cpuChart.data.labels=labels; cpuChart.data.datasets[0].data=cpuData; cpuChart.update();
        memChart.data.labels=labels; memChart.data.datasets[0].data=memData; memChart.update();
        diskChart.data.labels=labels; diskChart.data.datasets[0].data=diskData; diskChart.update();
      });
    }
    // Chart.js 플러그인 및 고급 옵션 적용
    const cpuCtx = document.getElementById('cpuChart').getContext('2d');
    const memCtx = document.getElementById('memChart').getContext('2d');
    const diskCtx = document.getElementById('diskChart').getContext('2d');
    const cpuChart = new Chart(cpuCtx,{
      type:'line',
      data:{labels:[],datasets:[{label:'CPU Load',data:[],borderColor:'#005BAC',backgroundColor:getGradient(cpuCtx,'#005BAC'),borderWidth:4,tension:0.5,pointRadius:7,pointBackgroundColor:'#fff',pointBorderColor:'#005BAC',pointBorderWidth:3,fill:true,shadowOffsetX:0,shadowOffsetY:2,shadowBlur:8,shadowColor:'rgba(0,91,172,0.18)'}]},
      options:{
        plugins:{
          legend:{display:true,labels:{color:'#005BAC',font:{weight:'bold',size:16}}},
          tooltip:{backgroundColor:'#005BAC',titleColor:'#fff',bodyColor:'#fff',borderColor:'#fff',borderWidth:2},
          datalabels:{color:'#005BAC',font:{weight:'bold'},align:'top',display:true,formatter:v=>v.toFixed(2)}
        },
        animation:{duration:1200,easing:'easeOutBounce'},
        scales:{y:{beginAtZero:true,grid:{color:'#e3f0ff'},ticks:{color:'#005BAC',font:{weight:'bold'}}},x:{grid:{color:'#e3f0ff'},ticks:{color:'#005BAC'}}}
      },
      plugins:[ChartDataLabels]
    });
    const memChart = new Chart(memCtx,{
      type:'line',
      data:{labels:[],datasets:[{label:'Memory Used (GB)',data:[],borderColor:'#43e97b',backgroundColor:getGradient(memCtx,'#43e97b'),borderWidth:4,tension:0.5,pointRadius:7,pointBackgroundColor:'#fff',pointBorderColor:'#43e97b',pointBorderWidth:3,fill:true}]},
      options:{
        plugins:{
          legend:{display:true,labels:{color:'#43e97b',font:{weight:'bold',size:16}}},
          tooltip:{backgroundColor:'#43e97b',titleColor:'#fff',bodyColor:'#fff'},
          datalabels:{color:'#43e97b',font:{weight:'bold'},align:'top',display:true,formatter:v=>v.toFixed(1)}
        },
        animation:{duration:1200,easing:'easeOutBounce'},
        scales:{y:{beginAtZero:true,grid:{color:'#e3f0ff'},ticks:{color:'#43e97b',font:{weight:'bold'}}},x:{grid:{color:'#e3f0ff'},ticks:{color:'#43e97b'}}}
      },
      plugins:[ChartDataLabels]
    });
    const diskChart = new Chart(diskCtx,{
      type:'line',
      data:{labels:[],datasets:[{label:'Disk Used (GB)',data:[],borderColor:'#FFB300',backgroundColor:getGradient(diskCtx,'#FFB300'),borderWidth:4,tension:0.5,pointRadius:7,pointBackgroundColor:'#fff',pointBorderColor:'#FFB300',pointBorderWidth:3,fill:true}]},
      options:{
        plugins:{
          legend:{display:true,labels:{color:'#FFB300',font:{weight:'bold',size:16}}},
          tooltip:{backgroundColor:'#FFB300',titleColor:'#fff',bodyColor:'#fff'},
          datalabels:{color:'#FFB300',font:{weight:'bold'},align:'top',display:true,formatter:v=>v.toFixed(1)}
        },
        animation:{duration:1200,easing:'easeOutBounce'},
        scales:{y:{beginAtZero:true,grid:{color:'#fffbe3'},ticks:{color:'#FFB300',font:{weight:'bold'}}},x:{grid:{color:'#fffbe3'},ticks:{color:'#FFB300'}}}
      },
      plugins:[ChartDataLabels]
    });
    fetchStatus(); setInterval(fetchStatus, 5000);
  </script>
</body>
</html> 