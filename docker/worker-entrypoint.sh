#!/bin/bash
# Start de drie AI-workers naast elkaar in één container.
#
# Elke worker is een eigen proces met een eigen wachtrij (zie bin/README.md),
# zodat een docent die een vraag ontwerpt niet achter een bulkbeoordeling
# hoeft te wachten. Stopt één worker (bijvoorbeeld door een fout in
# config.py), dan stopt dit script de andere twee ook en eindigt de container
# met een foutcode. De restart-policy van Docker start ze dan samen opnieuw;
# een container waarin stilletjes maar twee van de drie draaien valt niemand op.
set -u

CONFIG_SRC="/config/config.py"
CONFIG_DST="/app/bin/config.py"
WORKERS=(process_ai_feedback process_design_jobs process_assessment_jobs)

if [ ! -f "$CONFIG_SRC" ]; then
    echo "Geen $CONFIG_SRC gevonden. Mount een config.py (zie bin/config.py.sample) op $CONFIG_SRC." >&2
    exit 1
fi

# Kopiëren in plaats van rechtstreeks mounten in /app/bin: zo maakt het niet
# uit welke eigenaar en rechten het bestand op de host heeft (bijvoorbeeld
# 0600), en kan de worker-gebruiker het lezen maar niet wijzigen.
install -o root -g worker -m 0640 "$CONFIG_SRC" "$CONFIG_DST"

pids=()

stop_workers() {
    kill -TERM "${pids[@]}" 2>/dev/null
    wait
}

# docker stop: de workers zonder foutcode stoppen. Een job die halverwege
# wordt afgebroken blijft in de wachtrij van de webapp staan en wordt bij de
# volgende start opnieuw opgehaald.
trap 'trap - TERM INT; stop_workers; exit 0' TERM INT

for name in "${WORKERS[@]}"; do
    # Elke regel krijgt de naam van de worker als prefix, zodat de drie
    # logstromen in "docker logs" uit elkaar te houden zijn. Via process
    # substitution is $! het pid van python zelf, niet dat van sed.
    setpriv --reuid=worker --regid=worker --clear-groups \
        python "$name.py" > >(sed -u "s/^/[$name] /") 2>&1 &
    pids+=($!)
    echo "[entrypoint] $name gestart (pid $!)"
done

wait -n "${pids[@]}"
status=$?

echo "[entrypoint] Een worker is gestopt (exitcode $status); de andere workers worden ook gestopt." >&2
stop_workers
# Ook een worker die met 0 stopt is een fout: ze horen eindeloos te draaien.
exit $(( status == 0 ? 1 : status ))
