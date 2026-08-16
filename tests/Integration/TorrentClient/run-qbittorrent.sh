#!/usr/bin/env bash

set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)
runtime_dir=$(mktemp -d)
artifact_dir=${QBITTORRENT_INTEGRATION_ARTIFACT_DIR:-"${runtime_dir}/artifacts"}
probe="${repo_root}/tests/Integration/TorrentClient/QbittorrentProbe.php"
torrent_file="${runtime_dir}/client.torrent"
state_file="${runtime_dir}/state.json"
qb_home="${runtime_dir}/qb-home"
download_dir="${runtime_dir}/downloads"
laravel_log="${artifact_dir}/laravel.log"
qbittorrent_log="${artifact_dir}/qbittorrent.log"
qbittorrent_state="${artifact_dir}/qbittorrent-torrents.json"
laravel_pid=''
qbittorrent_pid=''

mkdir -p "${artifact_dir}" "${qb_home}/.config/qBittorrent" "${download_dir}"

stop_processes() {
    if [[ -n "${qbittorrent_pid}" ]] && kill -0 "${qbittorrent_pid}" 2>/dev/null; then
        kill "${qbittorrent_pid}" 2>/dev/null || true
    fi

    if [[ -n "${laravel_pid}" ]] && kill -0 "${laravel_pid}" 2>/dev/null; then
        kill "${laravel_pid}" 2>/dev/null || true
    fi
}

show_diagnostics() {
    curl --silent --show-error --max-time 5 \
        http://127.0.0.1:18080/api/v2/torrents/info \
        > "${qbittorrent_state}" || true
    php "${probe}" verify "${state_file}" --explain || true

    if [[ -f "${laravel_log}" ]]; then
        tail -n 100 "${laravel_log}"
    fi

    if [[ -f "${qbittorrent_log}" ]]; then
        tail -n 100 "${qbittorrent_log}"
    fi
}

on_exit() {
    status=$?
    trap - EXIT

    if [[ ${status} -ne 0 ]]; then
        show_diagnostics
    fi

    stop_processes
    exit "${status}"
}

trap on_exit EXIT

for dependency in curl php qbittorrent-nox; do
    if ! command -v "${dependency}" >/dev/null 2>&1; then
        echo "Missing required dependency: ${dependency}" >&2
        exit 2
    fi
done

cd "${repo_root}"
php "${probe}" prepare "${torrent_file}" "${state_file}"

php artisan serve --host=127.0.0.1 --port=18000 > "${laravel_log}" 2>&1 &
laravel_pid=$!

laravel_ready=false
for _ in $(seq 1 30); do
    if curl --silent --output /dev/null --max-time 1 http://127.0.0.1:18000/; then
        laravel_ready=true
        break
    fi

    sleep 1
done

if [[ "${laravel_ready}" != true ]]; then
    echo 'Laravel did not become ready for the qBittorrent integration test.' >&2
    exit 1
fi

printf '%s\n' \
    '[LegalNotice]' \
    'Accepted=true' \
    '' \
    '[Preferences]' \
    'Downloads\NewAdditionDialog=false' \
    'WebUI\Address=127.0.0.1' \
    'WebUI\HostHeaderValidation=false' \
    'WebUI\LocalHostAuth=false' \
    'WebUI\Port=18080' \
    > "${qb_home}/.config/qBittorrent/qBittorrent.conf"

HOME="${qb_home}" qbittorrent-nox --webui-port=18080 > "${qbittorrent_log}" 2>&1 &
qbittorrent_pid=$!

qbittorrent_ready=false
for _ in $(seq 1 30); do
    if curl --fail --silent --output /dev/null --max-time 1 \
        http://127.0.0.1:18080/api/v2/app/version; then
        qbittorrent_ready=true
        break
    fi

    sleep 1
done

if [[ "${qbittorrent_ready}" != true ]]; then
    echo 'qBittorrent WebUI API did not become ready.' >&2
    exit 1
fi

add_response=$(curl --fail --silent --show-error \
    --header 'Referer: http://127.0.0.1:18080' \
    --form "torrents=@${torrent_file};type=application/x-bittorrent" \
    --form "savepath=${download_dir}" \
    --form 'paused=false' \
    http://127.0.0.1:18080/api/v2/torrents/add)

if [[ "${add_response}" != 'Ok.' ]]; then
    echo "qBittorrent rejected the torrent: ${add_response}" >&2
    exit 1
fi

for _ in $(seq 1 60); do
    if php "${probe}" verify "${state_file}"; then
        exit 0
    fi

    sleep 1
done

echo 'qBittorrent did not produce a valid NXTGN announce within 60 seconds.' >&2
exit 1
