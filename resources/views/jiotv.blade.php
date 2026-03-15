<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Live TV | Smart TV Edition</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            overflow: hidden; 
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        
        .channel-btn {
            transition: all 0.2s ease-in-out;
            border: 2px solid transparent; 
            user-select: none;
            outline: none; 
        }
        
        /* TV Remote Focus State */
        .channel-btn:focus, .channel-btn:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: #3b82f6; 
            transform: scale(0.98);
        }

        .channel-btn.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(37, 99, 235, 0.2));
            border-color: #60a5fa;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.4);
        }

        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: #3b82f6;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="h-screen w-full flex flex-col md:flex-row" tabindex="0" id="main-body">

    <!-- Left Side: Video Player Area -->
    <div id="video-wrapper" class="flex-1 flex flex-col relative bg-black order-1 md:order-1 h-[45vh] md:h-full shrink-0 z-20 shadow-2xl" tabindex="0">
        
        <div class="absolute top-0 left-0 w-full p-4 bg-gradient-to-b from-black/90 to-transparent flex justify-between items-center z-10 pointer-events-none">
            <h1 class="text-xl font-bold text-white drop-shadow-md flex items-center gap-2">
                <i class="fas fa-satellite-dish text-blue-500"></i> DTV Stream
            </h1>
            <span id="now-playing-badge" class="bg-red-600/90 backdrop-blur text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider hidden shadow-lg animate-pulse">
                <i class="fas fa-circle mr-1 text-[8px]"></i> Live
            </span>
        </div>
        
        <!-- Iframe Container -->
        <div class="flex-1 relative w-full h-full bg-black flex items-center justify-center group" id="video-container">
            <iframe id="videoPlayer" src="" class="w-full h-full border-0" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
            
            <button onclick="toggleFullScreen()" class="absolute top-4 right-4 z-30 pointer-events-auto bg-black/70 hover:bg-blue-600 text-white w-12 h-12 rounded-lg backdrop-blur border border-white/20 shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all focus:opacity-100 focus:bg-blue-600 outline-none" title="Full Screen" tabindex="0">
                <i class="fas fa-expand text-xl"></i>
            </button>

            <div id="video-loader" class="absolute inset-0 bg-black flex flex-col items-center justify-center z-20">
                <div class="spinner mb-4"></div>
                <p id="loader-text" class="text-sm text-blue-400 font-bold tracking-widest uppercase">Connecting...</p>
            </div>
        </div>

        <div class="bg-slate-900 border-t border-slate-800 p-4 flex items-center gap-4 shrink-0 z-10">
            <img id="current-logo" src="https://via.placeholder.com/60?text=TV" class="w-14 h-14 rounded-lg object-contain bg-slate-800 p-1 border border-slate-700">
            <div class="flex-1 min-w-0">
                <h2 id="current-title" class="text-lg font-bold text-white truncate">Select a channel</h2>
                <p id="current-status" class="text-sm text-slate-400 truncate">Waiting for selection...</p>
            </div>
            <!-- TV Remote Hint -->
            <div class="hidden md:flex flex-col items-end text-[10px] text-slate-500 font-mono">
                <span><i class="fas fa-arrows-alt-v mr-1"></i> CH +/- (PageUp/Dn) to switch</span>
                <span><i class="fas fa-hand-pointer mr-1"></i> Press OK x2 for Fullscreen</span>
            </div>
        </div>
    </div>

    <!-- Right Side: Channel List -->
    <div class="w-full md:w-[400px] glass-panel border-l border-slate-700 flex flex-col order-2 md:order-2 h-[55vh] md:h-full shrink-0 z-10 relative shadow-[-10px_0_20px_rgba(0,0,0,0.5)]">
        
        <div class="p-4 border-b border-slate-700/50 bg-slate-800/80 shrink-0 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-slate-200 text-sm uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-list text-blue-400"></i> Channels (<span id="channel-count">0</span>)
                </h2>
                <select id="category-filter" class="bg-slate-900 border border-slate-600 text-white text-xs rounded-md px-2 py-1.5 focus:outline-none focus:border-blue-500 cursor-pointer outline-none" tabindex="0">
                    <option value="all">Malayalam & Sports</option>
                    <option value="malayalam">Malayalam Only</option>
                    <option value="sports">Sports Only</option>
                </select>
            </div>
            
            <div class="relative">
                <input type="text" id="search" placeholder="Search..." class="w-full bg-slate-900/80 text-white text-sm rounded-lg py-3 px-4 pl-10 focus:outline-none focus:border-blue-500 border border-slate-600/50 transition-all outline-none" tabindex="0">
                <i class="fas fa-search absolute left-4 top-3.5 text-slate-400"></i>
            </div>
        </div>
        
        <div id="channel-list" class="flex-1 overflow-y-auto p-3 space-y-2 pb-8 focus:outline-none" tabindex="0">
            <div class="flex flex-col items-center justify-center h-full text-slate-400 space-y-4">
                <div class="spinner"></div>
                <p class="text-sm font-medium animate-pulse">Fetching Data...</p>
            </div>
        </div>
    </div>

    <script>
        const API_URL = "https://my-api-5e7.pages.dev/jiotv2.json";
        const PLAYER_BASE_URL = "https://dtvlive-apk.pages.dev/app/aioplayer?id=";
        
        let originalFilteredChannels = []; 
        let currentDisplayChannels = [];   
        let currentChannelIndex = 0; 

        // Broad keywords 
        const malayalamKeywords = [
            'asianet', 'surya', 'mazhavil', 'kairali', 'amrita', 'mathrubhumi', 
            'mediaone', 'media one', 'reporter', 'janam', '24', 'flowers', 'malayalam', 
            'news 18 kerala', 'news18 kerala', 'safari', 'kochutv', 'kochu tv', 'we tv', 
            'goodness', 'shalom', 'kaumudy', 'zee keralam', 'kerala'
        ];
        
        const sportsKeywords = [
            'sports', 'star sports', 'sony ten', 'sony sports', 'eurosport', 
            'sports18', 'sports 18', 'fan code', 'fancode', 'wwe', 'tennis', 
            'cricket', 'football', 'premier league', 'nba'
        ];

        function isMalayalam(channel) {
            const name = (channel.channel_name || "").toLowerCase();
            const lang = (channel.channel_language || channel.language || "").toLowerCase();
            const cat = (channel.channel_category || "").toLowerCase();
            if (lang.includes('malayalam') || cat.includes('malayalam')) return true;
            return malayalamKeywords.some(kw => name.includes(kw));
        }

        function isSports(channel) {
            const name = (channel.channel_name || "").toLowerCase();
            const cat = (channel.channel_category || channel.category || "").toLowerCase();
            const genre = (channel.channel_genre || "").toLowerCase();
            if (cat.includes('sports') || genre.includes('sports')) return true;
            return sportsKeywords.some(kw => name.includes(kw));
        }

        async function fetchChannels() {
            try {
                const response = await fetch(API_URL);
                if(!response.ok) throw new Error("API Fetch Failed");
                
                const allApiData = await response.json();
                
                let processedChannels = allApiData.map(ch => ({
                    ...ch,
                    is_mal: isMalayalam(ch),
                    is_spt: isSports(ch)
                }));

                originalFilteredChannels = processedChannels.filter(ch => ch.is_mal || ch.is_spt);
                currentDisplayChannels = [...originalFilteredChannels];
                
                renderChannels(currentDisplayChannels);
                
                if(currentDisplayChannels.length > 0) {
                    playChannel(currentDisplayChannels[0]);
                }
                
                document.getElementById('video-loader').classList.add('hidden');
                document.getElementById('main-body').focus();
                
            } catch (error) {
                console.error("Fetch Error:", error);
                document.getElementById('channel-list').innerHTML = `<div class="p-6 text-center text-red-400">Connection Error. Please reload.</div>`;
                document.getElementById('video-loader').classList.add('hidden');
            }
        }

        function renderChannels(channels) {
            const listContainer = document.getElementById('channel-list');
            document.getElementById('channel-count').innerText = channels.length;
            listContainer.innerHTML = '';

            channels.forEach(channel => {
                const btn = document.createElement('button');
                btn.className = `channel-btn w-full text-left p-2.5 rounded-xl bg-slate-800/60 flex items-center gap-3`;
                btn.id = `ch-${channel.channel_id}`;
                btn.tabIndex = 0; 
                
                let badgeHTML = '';
                if(channel.is_spt && channel.is_mal) badgeHTML = `<span class="bg-purple-900/80 text-purple-300 text-[10px] px-2 py-0.5 rounded border border-purple-700/50">Mal Sports</span>`;
                else if(channel.is_spt) badgeHTML = `<span class="bg-orange-900/80 text-orange-300 text-[10px] px-2 py-0.5 rounded border border-orange-700/50">Sports</span>`;
                else if(channel.is_mal) badgeHTML = `<span class="bg-green-900/80 text-green-300 text-[10px] px-2 py-0.5 rounded border border-green-700/50">Malayalam</span>`;

                btn.innerHTML = `
                    <div class="w-14 h-14 rounded-lg bg-white/5 flex items-center justify-center shrink-0 border border-slate-700/50 overflow-hidden relative">
                        <img src="${channel.channel_logo}" class="w-full h-full object-contain p-1" onerror="this.src='https://via.placeholder.com/48?text=TV'">
                    </div>
                    <div class="flex-1 min-w-0 flex flex-col justify-center">
                        <div class="text-base font-semibold text-slate-200 truncate group-hover:text-white">${channel.channel_name}</div>
                        <div class="flex items-center gap-2 mt-1">
                            ${badgeHTML}
                            <span class="text-[10px] text-slate-500 font-mono">ID:${channel.channel_id}</span>
                        </div>
                    </div>
                `;

                // SMART TV DOUBLE TAP LOGIC
                let clickTimer = null;
                btn.onclick = (e) => {
                    document.getElementById('main-body').focus(); 
                    
                    if (clickTimer == null) {
                        // First Tap: Load Channel
                        playChannel(channel);
                        clickTimer = setTimeout(() => {
                            clickTimer = null;
                        }, 500); 
                    } else {
                        // Second Tap within 500ms: Fullscreen
                        clearTimeout(clickTimer);
                        clickTimer = null;
                        toggleFullScreen();
                    }
                };

                listContainer.appendChild(btn);
            });
        }

        function playChannel(channel) {
            currentChannelIndex = currentDisplayChannels.findIndex(c => c.channel_id === channel.channel_id);

            document.querySelectorAll('.channel-btn').forEach(b => b.classList.remove('active'));
            const activeBtn = document.getElementById(`ch-${channel.channel_id}`);
            if(activeBtn) {
                activeBtn.classList.add('active');
                activeBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            document.getElementById('current-logo').src = channel.channel_logo;
            document.getElementById('current-title').innerText = channel.channel_name;
            document.getElementById('now-playing-badge').classList.remove('hidden');
            
            const statusEl = document.getElementById('current-status');
            statusEl.innerText = 'Connecting...';
            statusEl.className = "text-sm text-blue-400 truncate font-medium";
            
            const loader = document.getElementById('video-loader');
            loader.classList.remove('hidden');

            const iframe = document.getElementById('videoPlayer');
            iframe.src = PLAYER_BASE_URL + channel.channel_id;

            setTimeout(() => {
                loader.classList.add('hidden');
                statusEl.innerText = 'Stream Ready (Click play in video)';
                statusEl.className = "text-sm text-green-400 truncate font-medium";
            }, 3000);
        }

        function playNext() {
            if (currentDisplayChannels.length === 0) return;
            currentChannelIndex = (currentChannelIndex + 1) % currentDisplayChannels.length;
            playChannel(currentDisplayChannels[currentChannelIndex]);
        }

        function playPrev() {
            if (currentDisplayChannels.length === 0) return;
            currentChannelIndex = (currentChannelIndex - 1 + currentDisplayChannels.length) % currentDisplayChannels.length;
            playChannel(currentDisplayChannels[currentChannelIndex]);
        }

        function toggleFullScreen() {
            const container = document.getElementById('video-wrapper');
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                if (container.requestFullscreen) container.requestFullscreen();
                else if (container.webkitRequestFullscreen) container.webkitRequestFullscreen();
                else if (container.msRequestFullscreen) container.msRequestFullscreen();
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
                else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
            }
        }

        // ==========================================
        // AGGRESSIVE TV REMOTE INTERCEPTOR
        // ==========================================
        function handleRemoteKeys(e) {
            // Ignore if typing in the search box
            if(document.activeElement && document.activeElement.tagName === 'INPUT') return;

            const k = e.key;
            const c = e.keyCode;

            // PAGE UP / CHANNEL UP
            if (k === 'PageUp' || k === 'ArrowUp' || k === 'ChannelUp' || c === 33 || c === 38 || c === 427 || c === 176) {
                e.preventDefault(); // Stop the TV from scrolling the page
                e.stopPropagation(); // Stop the iframe from stealing the key
                playNext();
            }
            // PAGE DOWN / CHANNEL DOWN
            else if (k === 'PageDown' || k === 'ArrowDown' || k === 'ChannelDown' || c === 34 || c === 40 || c === 428 || c === 177) {
                e.preventDefault(); 
                e.stopPropagation();
                playPrev();
            }
            // FULLSCREEN TOGGLE (F key or F11)
            else if ((k && k.toLowerCase() === 'f') || c === 122) {
                e.preventDefault();
                toggleFullScreen();
            }
        }

        // Use capture phase (true) to intercept the remote signal at the absolute top level
        window.addEventListener('keydown', handleRemoteKeys, true); 

        // Aggressively steal focus back from the video player every 3 seconds
        // so the TV remote doesn't get permanently stuck inside the iframe
        setInterval(() => {
            if (document.activeElement && document.activeElement.tagName === 'IFRAME') {
                window.focus();
                document.getElementById('main-body').focus();
            }
        }, 3000);

        function applyFilters() {
            const searchTerm = document.getElementById('search').value.toLowerCase();
            const categoryMode = document.getElementById('category-filter').value;

            currentDisplayChannels = originalFilteredChannels.filter(channel => {
                let categoryMatch = false;
                if (categoryMode === 'all') categoryMatch = true;
                else if (categoryMode === 'malayalam' && channel.is_mal) categoryMatch = true;
                else if (categoryMode === 'sports' && channel.is_spt) categoryMatch = true;

                let searchMatch = channel.channel_name.toLowerCase().includes(searchTerm);
                return categoryMatch && searchMatch;
            });

            renderChannels(currentDisplayChannels);
        }

        document.getElementById('search').addEventListener('input', applyFilters);
        document.getElementById('category-filter').addEventListener('change', applyFilters);

        document.addEventListener('DOMContentLoaded', fetchChannels);
    </script>
</body>
</html>
