        document.addEventListener('DOMContentLoaded', function() {
            function refreshPropagation() {
                fetch(arcPropagation.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=arc_propagation_refresh&nonce=' + arcPropagation.nonce
                })
                .then(response => response.text())
                .then(html => {
                    document.querySelector('.arc-propagation-dashboard').outerHTML = html;
                    attachTileListeners();
                })
                .catch(error => console.log('Refresh failed:', error));
            }
            setInterval(refreshPropagation, arcPropagation.interval);

            let mapIndex = 0;
            const mapTile = document.getElementById('map-rotate');
            function rotateMap() {
                mapTile.innerHTML = '<iframe src="' + arcPropagation.maps[mapIndex] + '"></iframe>';
                mapIndex = (mapIndex + 1) % arcPropagation.maps.length;
            }
            if (mapTile) {
                rotateMap();
                setInterval(rotateMap, 30000);
            }

            function attachTileListeners() {
                document.querySelectorAll('.band-tile').forEach(tile => {
                    tile.addEventListener('dblclick', function() {
                        const band = this.dataset.band;
                        const fullscreen = document.createElement('div');
                        fullscreen.className = 'fullscreen';
                        fullscreen.innerHTML = '<div style="color: white; font-size: 20pt;">' + band + ': ' + this.querySelector('span:nth-child(2)').textContent + '</div>';
                        document.body.appendChild(fullscreen);
                        fullscreen.addEventListener('dblclick', function() {
                            fullscreen.remove();
                        });
                    });
                });
            }
            attachTileListeners();

            document.querySelectorAll('.menu-item').forEach(item => {
                item.addEventListener('click', function() {
                    const action = this.dataset.action;
                    if (action === 'settings') {
                        document.getElementById('settings-widget').style.display = 'block';
                    } else if (action === 'update') {
                        fetch('https://api.github.com/repos/[YOUR_GITHUB_USERNAME]/arc-propagation-widget/releases/latest')
                            .then(response => response.json())
                            .then(data => {
                                document.getElementById('version-widget').innerHTML = 'Version: 1.3 (Latest: ' + data.tag_name + ')';
                            })
                            .catch(() => {
                                document.getElementById('version-widget').innerHTML = 'Version: 1.3 (Update check failed)';
                            });
                    }
                });
            });

            const form = document.getElementById('arc-settings-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('action', 'arc_save_settings');
                    formData.append('nonce', arcPropagation.settings_nonce);

                    fetch(arcPropagation.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        const messages = document.getElementById('arc-portal-messages');
                        if (data.success) {
                            messages.innerHTML = '<div class="notice notice-success"><p>' + data.data + '</p></div>';
                            arcPropagation.interval = document.getElementById('refresh-interval').value * 60 * 1000;
                        } else {
                            messages.innerHTML = '<div class="notice notice-error"><p>' + data.data + '</p></div>';
                        }
                    })
                    .catch(error => console.log('Settings save failed:', error));
                });
            }
        });

        function saveSettings() {
            const interval = document.getElementById('refresh-interval').value * 60 * 1000;
            arcPropagation.interval = interval;
            alert('Settings saved locally! Refresh interval updated to ' + (interval / 60000) + ' minutes.');
        }