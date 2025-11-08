
import simpy
import random
import math
import statistics
import pandas as pd
import matplotlib.pyplot as plt
from matplotlib.animation import FuncAnimation, PillowWriter
import numpy as np 

# Optional: scipy t-critical if available
try:
    from scipy.stats import t
    have_scipy = True
except Exception:
    have_scipy = False

params = {
    'mean_interarrival': 3.0,            # Ortalama geliş aralığı (dakika)
    'n_assembly': 3,                     # assembly makineleri
    'n_inspection': 2,                   # inspector sayısı
    'n_rework': 2,                       # rework çalışanı
    'n_packaging': 3,                    # packaging çalışanı
    'fail_rate': 0.20,                   # inspection'da başarısız olma olasılığı
    'tri_assembly': (5.0, 8.0, 12.0),    # assembly triangular(min,mode,max)
    'exp_inspection': 4.0,               # inspection ortalama (exponential)
    'uni_rework': (6.0, 10.0),           # rework uniform(min,max)
    'norm_packaging': (3.0, 0.5),        # packaging normal(mean,sd)
    'sim_time': 480.0,                   # simülasyon süresi (dakika)
    'n_replications': 30,                # kaç replikasyon
    'seed_base': 1000                    # rastgele tohum taban değeri
}


# 2) Yardımcı fonksiyonlar

def safe_gauss(mu, sigma, min_val=0.001):
    """Normal dağılımdan örnekle, negatif gelirse min_val döndür."""
    x = random.gauss(mu, sigma)
    return x if x > min_val else min_val

def ci_of_mean(data, alpha=0.05):
    """Verilen liste için (mean, margin) döndürür. margin = yarı-genişlik (örn. mean ± margin)"""
    n = len(data)
    mean = statistics.mean(data)
    if n < 2:
        return mean, None
    s = statistics.stdev(data)
    df = n - 1
    if have_scipy:
        tcrit = t.ppf(1 - alpha/2, df)
    else:
        tcrit = 1.96  # yaklaşık (n>=30 için uygundur)
    margin = tcrit * s / math.sqrt(n)
    return mean, margin


EVENT_LOG = []  
# --- Normalizer: event ve station adlarını tek tipe indir ---
def norm_event(e):
    e = (e or "").strip().lower()
    if e in {"service_start", "start_service", "service-start", "servicestart"}:
        return "service_start"
    if e in {"service_end", "end_service", "service-end", "serviceend"}:
        return "service_end"
    if e in {"queue_enter", "queue-enter", "queueenter"}:
        return "queue_enter"
    if e in {"depart", "departure", "leave"}:
        return "depart"
    return e

def norm_station(s):
    if s is None:
        return None
    mapping = {
        "assembly": "Assembly",
        "inspection": "Inspection",
        "rework": "Rework",
        "packaging": "Packaging"
    }
    key = str(s).strip().lower()
    return mapping.get(key, s)


def log_event(t, etype, station, entity):
    """
    etype: 'queue_enter' | 'service_start' | 'service_end' | 'depart'
    station: 'Assembly' | 'Inspection' | 'Rework' | 'Packaging' | None (depart için)
    """
    EVENT_LOG.append((float(t), etype, station, entity))


class Monitor:
    """Zaman-ağırlıklı kuyruk uzunluğu, busy-area, bekleme örnekleri, sistem zamanları."""
    def __init__(self, env, capacities):
        self.env = env
        self.data = {}
        for name, cap in capacities.items():
            self.data[name] = {
                'capacity': cap,
                'last_q_time': env.now,
                'last_q_len': 0,
                'area_q': 0.0,
                'q_trace': [(env.now, 0)],

                'last_b_time': env.now,
                'last_busy': 0,
                'busy_area': 0.0,
                'b_trace': [(env.now, 0)],

                'wait_times': []
            }
        self.system_times = []

    def queue_change(self, name, delta):
        r = self.data[name]
        now = self.env.now
        r['area_q'] += (now - r['last_q_time']) * r['last_q_len']
        r['last_q_time'] = now
        r['last_q_len'] += delta
        r['q_trace'].append((now, r['last_q_len']))

    def server_change(self, name, delta):
        r = self.data[name]
        now = self.env.now
        r['busy_area'] += (now - r['last_b_time']) * r['last_busy']
        r['last_b_time'] = now
        r['last_busy'] += delta
        r['b_trace'].append((now, r['last_busy']))

    def record_wait(self, name, wait):
        self.data[name]['wait_times'].append(wait)

    def record_system_time(self, t):
        self.system_times.append(t)

    def finalize(self, until):
        for name, r in self.data.items():
            now = until
            r['area_q'] += (now - r['last_q_time']) * r['last_q_len']
            r['busy_area'] += (now - r['last_b_time']) * r['last_busy']

    def results(self, until):
        out = {}
        for name, r in self.data.items():
            q_avg = r['area_q'] / until
            util = r['busy_area'] / (r['capacity'] * until) if r['capacity'] > 0 else 0.0
            avg_wait = statistics.mean(r['wait_times']) if r['wait_times'] else 0.0
            out[name] = {
                'time_avg_q': q_avg,
                'util': util,
                'avg_wait': avg_wait,
                'n_wait_samples': len(r['wait_times'])
            }
        out['system'] = {
            'avg_time_in_system': statistics.mean(self.system_times) if self.system_times else 0.0,
            'n': len(self.system_times)
        }
        return out

# 4) Süreçler: phone_process ve arrival_generator

def phone_process(env, name, params, resources, monitor):
    arrival_time = env.now

# Assembly
    q_enter = env.now
    monitor.queue_change('Assembly', +1)
    log_event(env.now, 'queue_enter', 'Assembly', name)
    with resources['assembly'].request() as req:
        yield req
        monitor.queue_change('Assembly', -1)
        wait = env.now - q_enter
        monitor.record_wait('Assembly', wait)
        monitor.server_change('Assembly', +1)
        log_event(env.now, 'service_start', 'Assembly', name)
        a = params['tri_assembly']
        service = random.triangular(a[0], a[1], a[2])
        yield env.timeout(service)
        monitor.server_change('Assembly', -1) 
        log_event(env.now, 'service_end', 'Assembly', name)  




    # Inspection ve olası rework döngüsü
    while True:
        q_enter = env.now
        monitor.queue_change('Inspection', +1)
        log_event(env.now, 'queue_enter', 'Inspection', name)
        with resources['inspection'].request() as req:
            yield req
            monitor.queue_change('Inspection', -1)
            wait = env.now - q_enter
            monitor.record_wait('Inspection', wait)
            monitor.server_change('Inspection', +1)
            log_event(env.now, 'service_start', 'Inspection', name)
            service = random.expovariate(1.0 / params['exp_inspection'])
            yield env.timeout(service)
            monitor.server_change('Inspection', -1)
            log_event(env.now, 'service_end', 'Inspection', name)


        if random.random() < params['fail_rate']:
            # Rework
            q_enter = env.now
            monitor.queue_change('Rework', +1)
            log_event(env.now, 'queue_enter', 'Rework', name)
            with resources['rework'].request() as req:
                yield req
                monitor.queue_change('Rework', -1)
                wait = env.now - q_enter
                monitor.record_wait('Rework', wait)
                monitor.server_change('Rework', +1)
                log_event(env.now, 'service_start', 'Rework', name)
                
                r = params['uni_rework']
                service = random.uniform(r[0], r[1])
                yield env.timeout(service)
                monitor.server_change('Rework', -1)
                log_event(env.now, 'service_end', 'Rework', name)
            # rework sonunda tekrar inspection'a dönülecek (while döngüsü devam)
            continue
        else:
            break

    # Packaging
    q_enter = env.now
    monitor.queue_change('Packaging', +1)
    log_event(env.now, 'queue_enter', 'Packaging', name)
    with resources['packaging'].request() as req:
        yield req
        monitor.queue_change('Packaging', -1)
        wait = env.now - q_enter
        monitor.record_wait('Packaging', wait)
        monitor.server_change('Packaging', +1)
        log_event(env.now, 'service_start', 'Packaging', name)

        p = params['norm_packaging']
        service = safe_gauss(p[0], p[1])
        yield env.timeout(service)
        monitor.server_change('Packaging', -1)
        log_event(env.now, 'service_end', 'Packaging', name)

    # System time kaydı
    monitor.record_system_time(env.now - arrival_time)
    log_event(env.now, 'depart', None, name)


def arrival_generator(env, params, resources, monitor):
    i = 0
    while True:
        inter = random.expovariate(1.0 / params['mean_interarrival'])
        yield env.timeout(inter)
        i += 1
        env.process(phone_process(env, f'Phone_{i}', params, resources, monitor))

# 5) Tek replikasyon çalıştırma
def run_one_replication(params, seed=None):
    if seed is not None:
        random.seed(seed)
    env = simpy.Environment()

    resources = {
        'assembly': simpy.Resource(env, capacity=params['n_assembly']),
        'inspection': simpy.Resource(env, capacity=params['n_inspection']),
        'rework': simpy.Resource(env, capacity=params['n_rework']),
        'packaging': simpy.Resource(env, capacity=params['n_packaging'])
    }

    monitor = Monitor(env, {
        'Assembly': params['n_assembly'],
        'Inspection': params['n_inspection'],
        'Rework': params['n_rework'],
        'Packaging': params['n_packaging']
    })

    env.process(arrival_generator(env, params, resources, monitor))
    env.run(until=params['sim_time'])
    monitor.finalize(params['sim_time'])
    res = monitor.results(params['sim_time'])
    return res, monitor


# 6) Çoklu replikasyon ve toplama
def aggregate_replication_results(rep_results):
    stations = ['Assembly', 'Inspection', 'Rework', 'Packaging']
    rows = []
    for st in stations:
        waits = [r[st]['avg_wait'] for r in rep_results]
        q_avgs = [r[st]['time_avg_q'] for r in rep_results]
        utils = [r[st]['util']*100.0 for r in rep_results]
        mean_wait, ci_wait = ci_of_mean(waits)
        mean_q, ci_q = ci_of_mean(q_avgs)
        mean_util, ci_util = ci_of_mean(utils)
        rows.append({
            'station': st,
            'mean_wait': mean_wait,
            'ci_wait': ci_wait,
            'mean_q': mean_q,
            'ci_q': ci_q,
            'mean_util_pct': mean_util,
            'ci_util_pct': ci_util
        })
    sys_times = [r['system']['avg_time_in_system'] for r in rep_results]
    mean_sys, ci_sys = ci_of_mean(sys_times)
    df = pd.DataFrame(rows)
    return df, (mean_sys, ci_sys)


def run_dss(params):
    reps = []
    for k in range(params['n_replications']):
        seed = params.get('seed_base', 1000) + k
        res, _mon = run_one_replication(params, seed=seed)
        reps.append(res)
    df, sys_ci = aggregate_replication_results(reps)
    return df, sys_ci


# 7) Raporlama ve grafik kaydetme
def print_report(df, sys_ci):
    pd.options.display.float_format = '{:,.3f}'.format
    print("\n=== Summary KPIs (mean ± 95% CI where computed) ===\n")
    print(df.to_string(index=False, float_format='{:,.3f}'.format))
    print(f"\nAverage time in system (mean ± CI): {sys_ci[0]:.3f} ± {sys_ci[1] if sys_ci[1] is not None else 'N/A'} min")

    print("\n=== Recommendations (heuristic) ===")
    for _, row in df.iterrows():
        if row['mean_util_pct'] > 90:
            print(f"- HIGH UTILIZATION: {row['station']} ≈ {row['mean_util_pct']:.1f}% — consider adding capacity.")
        if row['mean_wait'] > 5:
            print(f"- LONG WAITS: {row['station']} avg wait ≈ {row['mean_wait']:.2f} min — consider adding capacity or speeding service.")
    print("\n(Recommendations are simple heuristics.)")

def plot_and_save(df):
    # Utilization bar
    plt.figure(figsize=(8,4))
    plt.bar(df['station'], df['mean_util_pct'])
    plt.ylim(0, 110)
    plt.ylabel('Utilization (%)')
    plt.title('Mean Utilization per Station')
    plt.grid(axis='y', linestyle='--', alpha=0.3)
    plt.tight_layout()
    plt.savefig('utilization.png')
    plt.close()

    # Average waits with CI
    plt.figure(figsize=(8,4))
    waits = df['mean_wait']
    cis = df['ci_wait'].fillna(0)
    plt.bar(df['station'], waits, yerr=cis, capsize=5)
    plt.ylabel('Avg Wait (min)')
    plt.title('Average Wait per Station (with 95% CI)')
    plt.grid(axis='y', linestyle='--', alpha=0.3)
    plt.tight_layout()
    plt.savefig('avg_waits.png')
    plt.close()


def animate_queues(monitor, save_path='queues.gif', fps=10, max_frames=800):
    """
    Monitor.q_trace verisini kullanarak kuyruk uzunluklarını zaman içinde animasyon haline getirir.
    GIF'yi save_path olarak kaydeder.
    """
    stations = ['Assembly', 'Inspection', 'Rework', 'Packaging']
    # Her istasyonun q_trace'ini al
    traces = {st: monitor.data[st]['q_trace'][:] for st in stations}
    # Tüm zaman noktalarının birleşimi (kuyruk değişim anları)
    all_times = sorted({t for st in stations for (t, _) in traces[st]})
    if len(all_times) == 0:
        print("Animasyon için iz verisi yok.")
        return

    # Çok fazla kare olmasın diye seyrekleştir
    step = max(1, len(all_times) // max_frames)
    times = all_times[::step]

    # Her istasyon için geçerli indeks ve anlık kuyruk uzunluğu
    idx = {st: 0 for st in stations}
    current_q = {st: traces[st][0][1] if len(traces[st]) > 0 else 0 for st in stations}

    # Grafik kurulumu
    fig, ax = plt.subplots(figsize=(8, 4))
    bars = ax.bar(stations, [0]*len(stations))
    ax.set_ylim(0, max(1, max(q for st in stations for _, q in traces[st]) * 1.2))
    ax.set_ylabel('Kuyruk Uzunluğu')
    ax.set_title('Kuyruk Uzunlukları (Zaman İçinde)')

    # Her istasyonun trace’inde ilerlemek için yardımcı
    next_val_cache = {st: traces[st][1][1] if len(traces[st]) > 1 else current_q[st] for st in stations}

    def update(frame_i):
        t = times[frame_i]
        # Her istasyon için, bu zamana kadar olan değişimleri uygula
        for st in stations:
            tr = traces[st]
            # Bir sonraki kayıt bu zamana kadar geçtiyse, indexi ilerlet
            while idx[st] + 1 < len(tr) and tr[idx[st] + 1][0] <= t:
                idx[st] += 1
            current_q[st] = tr[idx[st]][1] if len(tr) > 0 else 0

        # Çubukları güncelle
        for b, st in zip(bars, stations):
            b.set_height(current_q[st])

        ax.set_title(f'Kuyruk Uzunlukları — t={t:.1f} dk')
        return bars

    anim = FuncAnimation(fig, update, frames=len(times), interval=1000/fps, blit=False)
    # GIF olarak kaydet
    try:
        anim.save(save_path, writer=PillowWriter(fps=fps))
        print(f"Animasyon kaydedildi: {save_path}")
    except Exception as e:
        print("GIF kaydederken sorun oluştu:", e)
    plt.close(fig)


def animate_flow_from_events(event_log, save_path='flow.gif', fps=12, tween_steps=6):
    import numpy as np
    stations = ['Assembly', 'Inspection', 'Rework', 'Packaging']
    x_pos = {st: i*3.0 for i, st in enumerate(stations)}
    queue_dx = -0.8
    queue_dy = 0.45
    service_y = -0.2

    # 1) Normalize et ve sırala
    cleaned = []
    for (t, etype, st, ent) in event_log:
        cleaned.append((float(t), norm_event(etype), norm_station(st), ent))
    if not cleaned:
        print("Uyarı: EVENT_LOG boş; log_event çağrıları ekli mi, env.run() sonra mı çağırdın?")
        return

    prio = {'queue_enter': 0, 'service_start': 1, 'service_end': 2, 'depart': 3}
    events = sorted(cleaned, key=lambda e: (e[0], prio.get(e[1], 99)))
    print(f"[animate] events loaded: {len(events)} (ilk 5): {events[:5]}")

    # Sistem durumu
    queues = {st: [] for st in stations}
    service = {st: None for st in stations}
    ent_pos = {}     # entity -> (x, y)
    active = set()

    # Yardımcılar
    def queue_target(st, entity):
        j = queues[st].index(entity)
        return (x_pos[st] + queue_dx, j * queue_dy)

    def service_target(st):
        return (x_pos[st], service_y)

    def snapshot():
        xs, ys = [], []
        # sıra; tutarlı görünsün diye queues ve service'den türet
        # önce kuyruktakiler
        for st in stations:
            for ent in queues[st]:
                p = ent_pos.get(ent, (x_pos[st] + queue_dx, queues[st].index(ent)*queue_dy))
                xs.append(p[0]); ys.append(p[1])
        # sonra servistekiler
        for st in stations:
            ent = service[st]
            if ent is not None and ent in ent_pos:
                p = ent_pos[ent]
                xs.append(p[0]); ys.append(p[1])
        return np.column_stack([xs, ys]) if len(xs) else np.empty((0,2))

    frames = []  # (time, offsets ndarray)

    def tween_move(entity, to_xy, steps, now_time):
        # var olan konumdan lineer tween
        if entity not in ent_pos:
            ent_pos[entity] = to_xy
            frames.append((now_time, snapshot()))
            return
        x0, y0 = ent_pos[entity]
        x1, y1 = to_xy
        for k in range(1, steps + 1):
            alpha = k / steps
            ent_pos[entity] = (x0 + (x1 - x0) * alpha, y0 + (y1 - y0) * alpha)
            frames.append((now_time, snapshot()))

    # Olayları işle
    for (t, etype, st, ent) in events:
        now_time= t
        if etype == 'queue_enter' and st:
            active.add(ent)
            queues[st].append(ent)
            # hedef pozisyonu hesapla
            target = queue_target(st, ent)
            # başlangıç pozisyonu (soldan gelir)
            if ent not in ent_pos:
                ent_pos[ent] = (x_pos[st] + queue_dx - 1.2, target[1])
            tween_move(ent, target, tween_steps, now_time)

        elif etype == 'service_start' and st:
            # kuyruğun başından çıkar (güvenli)
            if ent in queues[st]:
                queues[st].remove(ent)
            service[st] = ent
            target = service_target(st)

            # kalanların yukarı kayması: küçük tween ile yumuşat
            for j, other in enumerate(queues[st]):
                oldx, oldy = ent_pos.get(other, (x_pos[st] + queue_dx, j * queue_dy))
                newpos = (x_pos[st] + queue_dx, j * queue_dy)
                if abs(oldy - newpos[1]) > 1e-6:
                    small_steps = max(1, tween_steps // 3)
                    for k in range(1, small_steps + 1):
                        alpha = k / small_steps
                        ent_pos[other] = (oldx, oldy + (newpos[1] - oldy) * alpha)
                        frames.append((now_time, snapshot()))
                else:
                    ent_pos[other] = newpos

            # servise giren kişi için tween
            tween_move(ent, target, tween_steps, now_time)

        elif etype == 'service_end' and st:
            if service[st] == ent:
                service[st] = None
            # bir kare beklet (görsel durma)
            frames.append((now_time, snapshot()))

        elif etype == 'depart':
            if ent in ent_pos:
                exit_target = (max(x_pos.values()) + 1.5, ent_pos[ent][1])
                tween_move(ent, exit_target, tween_steps, now_time)
                if ent in active:
                    active.remove(ent)
                if ent in ent_pos:
                    del ent_pos[ent]
            frames.append((now_time, snapshot()))
        else:
            frames.append((now_time, snapshot()))

    if not frames:
        print("Uyarı: frames boş kaldı, EVENT_LOG veya event tipleri kontrol et.")
        return

    # Çizim
    fig, ax = plt.subplots(figsize=(10, 4))
    ax.set_xlim(-1.5, max(x_pos.values()) + 2.0)
    ax.set_ylim(-1.0, max(6.0, len(max(queues.values(), key=len, default=[])) * 1.0 + 2.0))
    ax.set_xlabel('İstasyonlar')
    ax.set_ylabel('Kuyruk/Süreç konumu')
    for st in stations:
        ax.text(x_pos[st], ax.get_ylim()[1] - 0.2, st, ha='center', va='bottom', fontsize=10)

    scat = ax.scatter([], [])
    title_text = ax.text(0.02, 0.95, '', transform=ax.transAxes, ha='left', va='top')

    def init():
        scat.set_offsets(np.empty((0,2)))
        title_text.set_text('Üretim Akışı — t=0.0 dk')
        return scat, title_text

    def update(i):
        offsets = frames[i][1]
        if offsets is None or offsets.size == 0:
            scat.set_offsets(np.empty((0, 2)))
        else:
            scat.set_offsets(offsets)

        current_time = frames[i][0]
        title_text.set_text(f'Üretim Akışı — t≈{current_time:.1f} dk')
        return scat, title_text




    anim = FuncAnimation(fig, update, init_func=init, frames=len(frames), interval=1000/fps, blit=False)
    try:
        anim.save(save_path, writer=PillowWriter(fps=fps))
        print(f"Hareketli akış animasyonu kaydedildi: {save_path}")
    except Exception as e:
        print("GIF kaydederken hata:", e)
    plt.close(fig)



if __name__ == '__main__':
    print("Running Smartphone Assembly DSS simulation...")

    # 1) Animasyon göstermek için TEK replikasyon çalıştır
    one_res, one_monitor = run_one_replication(params, seed=params.get('seed_base', 1000))
    # Simülasyon bitince monitor'de q_trace hazır; animasyonu üret
    print(">>> EVENT_LOG length:", len(EVENT_LOG))
    print(">>> sample events (ilk 20):", EVENT_LOG[:20])

    animate_queues(one_monitor, save_path='queues.gif', fps=10, max_frames=800)

    # 2) DSS raporu için ÇOKLU replikasyon çalıştır
    animate_flow_from_events(EVENT_LOG, save_path='flow.gif', fps=12, tween_steps=6)
    
    df, sys_ci = run_dss(params)
    print_report(df, sys_ci)
    plot_and_save(df)


    print("\nŞu dosyalar oluştu:")
    print("- queues.gif (kuyruk animasyonu)")
    print("- utilization.png (ortalama kullanım grafiği)")
    print("- avg_waits.png (ortalama bekleme + CI grafiği)")
  
