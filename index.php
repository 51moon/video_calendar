<?php
// index.php – calendar page (loads data from api.php)
declare(strict_types=1);
require __DIR__ . '/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Calendar</title>
    <link rel="icon" href="<?= htmlspecialchars(faviconHref(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="src/styles.css">
</head>
<body>
    <header class="page-header">
        <a class="logout-button" href="login.php?logout=1">Logout</a>
        <h1>Video Calendar</h1>
    </header>
    <p class="intro-text">Welcome! Open one door each day, watch the video and leave your rating and a short comment.</p>
    <div class="calendar" id="calendar"><em>Loading…</em></div>

    <script>
        const API = 'api.php';

        // Client-side state: one entry per door with
        // day, title, url, thumbnail, opened_date, rating, comment.
        // A door counts as opened when opened_date is not empty.
        const totalDoors = 24;
        let nextDoorToOpen = 1;
        let doors = [];

        function isOpened(index) {
            return !!(doors[index] && doors[index].opened_date);
        }

        // Fill in any missing doors and recalculate nextDoorToOpen
        function initDoors() {
            for (let i = 0; i < totalDoors; i++) {
                if (!doors[i]) {
                    doors[i] = {
                        day: (i + 1).toString(),
                        title: `Video ${i + 1}`,
                        url: `https://example.com/video${i + 1}`,
                        thumbnail: `src/thumbnails/${String(i + 1).padStart(2, '0')}.png`,
                        opened_date: '',
                        rating: '',
                        comment: ''
                    };
                }
            }

            let maxOpened = 0;
            doors.forEach((door, index) => {
                if (door.opened_date && index + 1 > maxOpened) {
                    maxOpened = index + 1;
                }
            });
            nextDoorToOpen = maxOpened + 1;
            if (nextDoorToOpen < 1) nextDoorToOpen = 1;
        }

        // Load doors from the server
        async function loadData() {
            const res = await fetch(API);
            if (!res.ok) throw new Error('Failed to load data');
            const data = await res.json();
            doors = data.doors || [];
        }

        // Save progress (opened_date, rating, comment) to the server
        async function saveData() {
            try {
                const response = await fetch(API, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        doors: doors.map((door, index) => ({
                            day: (index + 1).toString(),
                            opened_date: door.opened_date || '',
                            rating: door.rating || '',
                            comment: door.comment || ''
                        }))
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to save data');
                }

                console.log('Data saved successfully');
            } catch (error) {
                console.error('Error saving data:', error);
            }
        }

        // Create the doors in the DOM
        function createDoors() {
            const calendar = document.getElementById('calendar');
            calendar.innerHTML = '';

            for (let i = 0; i < totalDoors; i++) {
                const door = document.createElement('div');
                const video = doors[i] || {};
                const wasOpened = isOpened(i);
                const isLocked = i + 1 < nextDoorToOpen;

                door.className = `door ${wasOpened ? 'opened' : ''} ${isLocked ? 'locked' : ''}`;

                if (wasOpened) {
                    const thumbnailPath = video.thumbnail || `src/thumbnails/${String(i + 1).padStart(2, '0')}.png`;
                    const videoTitle = video.title || `Video ${i + 1}`;
                    const videoUrl = video.url || `https://example.com/video${i + 1}`;

                    door.innerHTML = `
                        <div class="door-number">${i + 1}</div>
                        <div class="video-title">${videoTitle}</div>
                        <a href="${videoUrl}" target="_blank" class="video-preview" title="${videoTitle}">
                            <img src="${thumbnailPath}" alt="Preview for ${videoTitle}" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23333%22 width=%22100%22 height=%22100%22/%3E%3Ctext fill=%22white%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3EVideo ${i + 1}%3C/text%3E%3C/svg%3E';">
                        </a>
                        <div class="rating" id="rating-${i}">
                            ${[1, 2, 3, 4, 5].map(n => `
                                <button onclick="rateDoor(${i}, ${n})" ${doors[i].rating == n ? 'class="selected"' : ''}>${n}</button>
                            `).join('')}
                        </div>
                        <div class="comment">
                            <textarea id="comment-${i}" placeholder="Your comment...">${(doors[i].comment || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                            <button class="save-comment" onclick="saveComment(${i})">Save</button>
                        </div>
                    `;
                } else {
                    door.innerHTML = `
                        <div class="door-number">${i + 1}</div>
                    `;
                }

                if (i + 1 === nextDoorToOpen || wasOpened) {
                    door.onclick = () => openDoor(i);
                }

                calendar.appendChild(door);
            }

            alignTitleHeights();
        }

        // Align title heights per calendar row: every opened door in a row
        // gets the height of the tallest title in that row, so thumbnails
        // and ratings start at the same vertical position.
        function alignTitleHeights() {
            const openedDoors = document.querySelectorAll('.door.opened');
            const titles = [];
            openedDoors.forEach(door => {
                const title = door.querySelector('.video-title');
                if (title) {
                    title.style.height = 'auto';
                    titles.push({ door, title });
                }
            });

            // Group doors by row (same vertical position in the grid)
            const rows = new Map();
            titles.forEach(({ door, title }) => {
                const y = door.offsetTop;
                if (!rows.has(y)) {
                    rows.set(y, []);
                }
                rows.get(y).push(title);
            });

            rows.forEach(rowTitles => {
                const maxHeight = Math.max(...rowTitles.map(t => t.offsetHeight));
                rowTitles.forEach(t => {
                    t.style.height = maxHeight + 'px';
                });
            });
        }

        // Open a door
        function openDoor(index) {
            if (index + 1 === nextDoorToOpen) {
                doors[index].opened_date = new Date().toISOString();
                nextDoorToOpen++;
                saveData();
                createDoors();
            }
        }

        // Rate a door
        function rateDoor(doorIndex, rating) {
            doors[doorIndex].rating = rating;
            saveData();
            createDoors();
        }

        // Save the comment of a door
        function saveComment(doorIndex) {
            const field = document.getElementById(`comment-${doorIndex}`);
            if (!field) return;
            doors[doorIndex].comment = field.value;
            saveData();
        }

        // Re-align when the window is resized (column count may change)
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(alignTitleHeights, 100);
        });

        // Initialize the page
        async function init() {
            await loadData();
            initDoors();
            createDoors();
        }

        // Start the application
        init().catch(err => {
            document.getElementById('calendar').innerHTML =
                '<span class="error">Failed to load calendar: ' + err.message + '</span>';
        });
    </script>
</body>
</html>
