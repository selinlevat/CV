import simpy
import random
import matplotlib.pyplot as plt
from matplotlib.animation import FuncAnimation

# --- Verileri tutmak için listeler ---
zaman_listesi = []
sistemdeki_yolcu_sayisi = []
bekleme_sureleri = []

# --- Simülasyon parametreleri ---
SIMULASYON_SURESI = 60  # dakika
CHECKIN_KAPASITE = 5

# --- Yolcu işlemleri ---
class HavalimaniSimulasyonu:
    def __init__(self, env, checkin_counter):
        self.env = env
        self.checkin_counter = checkin_counter
        self.yolcu_sayisi = 0
        self.aktif_yolcular = 0

    def passenger(self, name):
        arrival_time = self.env.now
        self.aktif_yolcular += 1
        print(f"{arrival_time:.2f} dk: {name} geldi. Sistemde: {self.aktif_yolcular} kişi")
        zaman_listesi.append(self.env.now)
        sistemdeki_yolcu_sayisi.append(self.aktif_yolcular)

        with self.checkin_counter.request() as request:
            yield request

            wait_time = self.env.now - arrival_time
            bekleme_sureleri.append(wait_time)
            print(f"{self.env.now:.2f} dk: {name} {wait_time:.2f} dk bekledi ve check-in başladı.")

            checkin_time = random.uniform(3, 5)
            yield self.env.timeout(checkin_time)

            self.aktif_yolcular -= 1
            print(f"{self.env.now:.2f} dk: {name} işlemi bitirdi. Sistemde: {self.aktif_yolcular} kişi")
            zaman_listesi.append(self.env.now)
            sistemdeki_yolcu_sayisi.append(self.aktif_yolcular)

    def yolcu_uretici(self):
        while True:
            yield self.env.timeout(random.expovariate(5))  # Her dakika 5 kişi
            self.yolcu_sayisi += 1
            self.env.process(self.passenger(f"Yolcu {self.yolcu_sayisi}"))

# --- Simülasyonu başlat ---
env = simpy.Environment()
checkin_counter = simpy.Resource(env, capacity=CHECKIN_KAPASITE)
sim = HavalimaniSimulasyonu(env, checkin_counter)
env.process(sim.yolcu_uretici())
env.run(until=SIMULASYON_SURESI)

# --- ANİMASYON GRAFİĞİ ---

fig, ax = plt.subplots()
line, = ax.plot([], [], lw=2)
ax.set_xlim(0, SIMULASYON_SURESI)
ax.set_ylim(0, max(sistemdeki_yolcu_sayisi) + 1)
ax.set_title("Sistem İçindeki Yolcu Sayısı")
ax.set_xlabel("Zaman (dk)")
ax.set_ylabel("Yolcu Sayısı")
ax.grid(True)

def init():
    line.set_data([], [])
    return line,

def update(frame):
    line.set_data(zaman_listesi[:frame], sistemdeki_yolcu_sayisi[:frame])
    return line,

ani = FuncAnimation(fig, update, frames=len(zaman_listesi), init_func=init,
                    blit=True, interval=200, repeat=False)

plt.tight_layout()
plt.show()
