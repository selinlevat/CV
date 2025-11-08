import simpy
import random
import matplotlib.pyplot as plt
from matplotlib.animation import FuncAnimation

# Veri listeleri
passenger_names = []
wait_times = []
checkin_start_times = []
checkin_end_times = []

# Sabitler
SIM_TIME =120
ARRIVAL_INTERVAL = [1, 3]
CHECKIN_DURATION = [3, 7]
NUM_COUNTERS = 10
RANDOM_SEED = 42

def passenger(env, name, checkin_counter):
    arrival_time = env.now
    print(f'{name} geldi - zaman: {arrival_time:.2f}')
    passenger_names.append(name)

    with checkin_counter.request() as request:
        yield request

        wait = env.now - arrival_time
        wait_times.append(wait)
        checkin_start_times.append(env.now)

        print(f'{name} check-in başlıyor - zaman: {env.now:.2f} (bekleme: {wait:.2f})')

        checkin_duration = random.randint(*CHECKIN_DURATION)
        yield env.timeout(checkin_duration)

        checkin_end_times.append(env.now)
        print(f'{name} check-in bitti - zaman: {env.now:.2f}')

def passenger_generator(env, checkin_counter):
    i = 0
    while True:
        yield env.timeout(random.randint(*ARRIVAL_INTERVAL))
        i += 1
        env.process(passenger(env, f'Passenger {i}', checkin_counter))

def run_simulation():
    random.seed(RANDOM_SEED)
    env = simpy.Environment()
    checkin_counter = simpy.Resource(env, capacity=NUM_COUNTERS)
    env.process(passenger_generator(env, checkin_counter))
    env.run(until=SIM_TIME)

    if wait_times:
        average_wait = sum(wait_times) / len(wait_times)
    else:
        average_wait = 0

    print("\n--- VERİ ÖZETİ ---")
    print("Toplam yolcu:", len(passenger_names))
    print("Ortalama bekleme süresi:", round(average_wait, 2), "dakika")

    for i in range(len(passenger_names)):
        print(f'{passenger_names[i]} | Bekleme: {wait_times[i]:.2f} dk | Check-in: {checkin_start_times[i]:.2f} → {checkin_end_times[i]:.2f}')

# Programı çalıştır
run_simulation()
