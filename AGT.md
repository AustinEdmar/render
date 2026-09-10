Fix: muda para um driver assíncrono de verdade. Como já tens Redis configurado (CACHE_STORE=redis, REDIS_HOST=redis):

dotenv
QUEUE_CONNECTION=redis

Ou, mais simples de operar sem infra extra, database (usa a tabela jobs, que criarias com php artisan queue:table && php artisan migrate).

Com qualquer um dos dois, precisas de um worker sempre a correr, senão os jobs ficam parados na fila para sempre:

bash
php artisan queue:work --queue=agt

Em produção isso tem de estar sob um supervisor de processos (Supervisor, ou o equivalente "background worker" no Render, já que vejo que usas Render para a base de dados) — não pode ser só um terminal aberto.