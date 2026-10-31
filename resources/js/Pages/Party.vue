<template>
  <div id="app">
    <div class="grain" aria-hidden="true"></div>
    <div class="snow-bg" aria-hidden="true">
      <span v-for="(s, i) in snow" :key="i" :style="{ left: s.left, top: s.top, fontSize: s.size, opacity: s.opacity }">❄</span>
    </div>

    <aside v-if="isHostView && hostSignedInLocal" class="admin-sidebar" aria-label="Host navigation">
      <div class="admin-brand">
        <div class="admin-brand-title">Host</div>
        <div v-if="setupDoneLocal" class="admin-brand-party">{{ localParty }}</div>
        <div class="admin-brand-label">Admin</div>
      </div>
      <nav class="admin-nav">
        <button class="admin-nav-btn" :class="{ active: view === 'hostdashboard' }" @click="openDashboard"><LayoutDashboard class="w-5 h-5" /><span>Dashboard</span></button>
        <button class="admin-nav-btn" :class="{ active: view === 'hostguests' }" @click="goTo('hostguests', '/host/guests')"><Users class="w-5 h-5" /><span>Guests</span></button>
        <button class="admin-nav-btn" :class="{ active: view === 'hostpoll' }" @click="goTo('hostpoll', '/host/poll')"><Vote class="w-5 h-5" /><span>Polls</span></button>
        <button class="admin-nav-btn" :class="{ active: view === 'hostsetup' }" @click="goTo('hostsetup', '/host/setup')"><Settings class="w-5 h-5" /><span>Party settings</span></button>
      </nav>
      <div class="admin-sidebar-foot">
        <p class="admin-signed-in">Signed in as <b>{{ hostUsernameLocal || 'host' }}</b></p>
        <button class="btn btn-sm w-full mt-2" @click="hostSignOut">Sign out</button>
        <button class="btn btn-ghost btn-sm w-full mt-2" @click="goHome">Back to guest home</button>
      </div>
    </aside>

    <main v-if="!isHostView || !hostSignedInLocal" class="w-full mx-auto px-3 py-3 max-w-2xl" style="position:relative;z-index:1;padding-bottom:96px">
      <!-- HOST SIGN-IN REQUIRED: no host content renders when signed out -->
      <section v-show="isHostView && !hostSignedInLocal">
        <div class="admin-signin">
          <h2 class="text-2xl font-black">Host sign-in required</h2>
          <p class="text-sm opacity-70 mt-1">Sign in with your host username and password to manage this party. Guests do not need to sign in — they join from the home page with the invite code.</p>
          <label class="block mt-4">Username <input class="input" v-model="hostUser" @keyup.enter="submitHostLogin" /></label>
          <label class="block mt-2">Password <input class="input" type="password" v-model="hostPass" @keyup.enter="submitHostLogin" /></label>
          <p v-if="hostLoginError" class="text-sm text-error mt-2">Incorrect username or password — try again.</p>
          <button class="btn btn-primary w-full mt-4" @click="submitHostLogin"><KeyRound class="w-5 h-5" /> Sign in as host</button>
          <button class="btn btn-ghost w-full mt-2" @click="goHome">Back to guest home</button>
        </div>
      </section>
      <!-- HOME -->
      <section v-show="view === 'home'">
        <div class="flex items-start gap-2">
          <div class="shrink-0 text-center">
            <ScorSanta :size="6" :class="{ 'santa-wave': santaWaving }" @click="waveSanta" />
          </div>
          <div class="santa-bubble flex-1"><span class="bubble-dot" aria-hidden="true"></span>
            <span v-if="!setupDoneLocal">Ho ho ho! The party isn't set up yet — check back once your host has set it up.</span>
            <span v-else>Ho ho ho! Scan the QR — you'll land straight on the guest page. Search your name, and let's get gifting.</span>
          </div>
        </div>
        <div class="segmented mt-4">
          <button class="seg-btn" :disabled="!setupDoneLocal" @click="startGuest"><Gift class="w-6 h-6" /> I'm a guest</button>
        </div>
        <button class="btn btn-outline w-full mt-3" @click="openQrModal"><QrCode class="w-5 h-5" /> Scan QR code</button>
        <p class="text-center mt-3"><a href="/host/dashboard" class="text-sm opacity-60" style="text-decoration:underline">Host sign-in</a></p>
        <p v-if="!setupDoneLocal" class="text-sm opacity-80 text-center mt-3">No party set up yet — the host needs to set it up first.</p>
        <p v-if="setupDoneLocal" class="text-sm opacity-80 text-center mt-3">Guest? Scan the QR code from your host, or open the direct link they sent you, then find your name.</p>
      </section>

      <!-- WHO -->
      <div v-if="view === 'who'" class="flex justify-start mb-3"><button class="text-sm font-bold opacity-80" style="background:none;border:none;color:inherit;cursor:pointer" @click="goHome">← Back to home</button></div>
      <div v-if="guestMe && isGuestView" class="flex items-center gap-2 text-sm mb-3">
        <p class="text-left flex-1 min-w-0">Wishing as: <b>{{ guestMe.display }}</b><span v-if="guestMe.nickname"> “{{ guestMe.nickname }}”</span><span v-if="guestRecipient"> • Buying for: <b>{{ guestRecipient.name }}</b></span></p>
        <button class="btn btn-ghost shrink-0 ml-auto" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Log out and return to the main home page" @click="guestLogout"><LogOut class="w-5 h-5" /></button>
      </div>

      <section v-show="view === 'who'">
        <div class="card m3-card p-4">
          <div class="flex gap-2 items-start">
            <ScorSanta :size="6" />
            <div class="santa-bubble santa-bubble-sm santa-bubble-wide flex-1"><span class="bubble-dot" aria-hidden="true"></span>First, find yourself! Type your name or nickname, then tap Search — I'll check if you're on the list, then tap your exact entry. You never type a name, so spelling can't mismatch.</div>
          </div>
          <h2 class="text-2xl font-black">Who are you?</h2>
          <p class="text-sm opacity-70 mt-1">Search your name, then confirm it's you.</p>
          <div class="flex gap-2 mt-3">
            <input class="input" v-model="search" placeholder="Type your name, nickname, or alias…" @keyup.enter="guestSearched = true" />
            <button class="btn btn-primary shrink-0" style="width:auto" @click="guestSearched = true"><Search class="w-5 h-5" /> Search</button>
          </div>
          <div v-if="guestSearched" class="mt-4">
            <div v-if="!filteredGuests.length" class="text-sm opacity-70">No match — try a nickname or just part of your name.</div>
            <button v-for="g in filteredGuests" :key="g.id" class="w-full text-left p-3 mt-2 card" style="border-radius:1rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:inherit;cursor:pointer" @click="selectGuest(g)">
              <b>{{ g.display }}</b><span v-if="g.nickname"> “{{ g.nickname }}”</span><span v-if="g.aliases && g.aliases.length" class="text-sm opacity-60"> (also: {{ g.aliases.join(', ') }})</span><span v-if="g.claimed" class="text-error font-bold"> — Locked</span>
            </button>
          </div>
        </div>
      </section>

      <!-- REVEAL -->
      <section v-show="view === 'reveal'">
        <div class="card m3-card p-4">
          <div class="flex gap-2 items-start">
            <ScorSanta :size="4" />
            <div class="santa-bubble santa-bubble-sm flex-1"><span class="bubble-dot" aria-hidden="true"></span>One tap and I'll show you who you're buying for — write it down, it only shows once!</div>
          </div>
          <div v-if="!guestRecipient">
            <h2 class="text-2xl font-black"><Gift class="w-6 h-6 inline" /> Your Secret Santa reveal</h2>
            <p class="mt-2">This will reveal your recipient <b>once</b>. After this, this name can't be used again — even by you.</p>
            <div class="alert alert-warning mt-3" style="border-radius:1rem">Note your recipient down before you move on. A fresh scan will only say it is already locked.</div>
            <button class="btn btn-success w-full mt-3" @click="doReveal">Show my recipient</button>
          </div>
          <div v-else class="text-center">
            <h2 class="text-2xl font-black"><Gift class="w-6 h-6 inline" /> You are buying for:</h2>
            <div class="text-2xl font-black my-4">{{ guestRecipient.name }}<span v-if="guestRecipient.nickname"> “{{ guestRecipient.nickname }}”</span></div>
            <p v-if="guestRecipient.budget" class="opacity-70">Gift budget: {{ formatBudget(guestRecipient.budget) }}</p>
            <div class="alert alert-warning mt-3 text-left" style="border-radius:1rem">Write it down now. This card stays here while this page is open, but a fresh scan will only say it is already locked.</div>
            <button class="btn btn-primary w-full mt-3" @click="openRecipientWishlist">I've noted it down — show their wishlist <ArrowRight class="w-4 h-4" /></button>
          </div>
        </div>
      </section>

      <!-- GUEST HOME -->
      <section v-show="view === 'guesthome'">
        <div class="card m3-card p-4">
          <h2 class="text-2xl font-black">Your Party</h2>
          <div class="grid grid-cols-2 gap-2 mt-4">
            <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"><p class="section-label">Party</p><p class="font-black mt-1">{{ localParty }}</p></div>
            <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"><p class="section-label">Locked</p><p class="font-black mt-1">{{ localGuests.filter(g => g.claimed).length }} / {{ localGuests.length }}</p><p class="text-sm opacity-80">names locked</p></div>
            <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"><p class="section-label">Budget ₱</p><p class="font-black mt-1">{{ formatBudget(localBudget) }}</p></div>
            <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"><p class="section-label">Gifts</p><p class="font-black mt-1">{{ myBoardItems.length }}</p></div>
          </div>
          <p class="text-sm opacity-70 mt-3">Use the dock below for Gifts, Wishlists and Vote.</p>
        </div>
        <div class="flex gap-2 items-end guesthome-scor">
          <ScorSanta :size="4" />
          <div class="santa-bubble santa-bubble-sm flex-1"><span class="bubble-dot" aria-hidden="true"></span>Welcome in! Use the dock to peek at wishlists and cast your vote.</div>
        </div>
      </section>

      <!-- GIFTS (my wishlist) -->
      <section v-show="view === 'gifts'">
        <div class="card m3-card p-4">
          <div class="flex gap-2 items-start mt-2">
            <ScorSanta :size="4" />
            <div class="santa-bubble santa-bubble-sm flex-1"><span class="bubble-dot" aria-hidden="true"></span>Add your own wishes so your giver knows what to get you.</div>
          </div>
          <div class="flex items-center justify-between gap-3 mt-2">
            <h3 class="text-xl font-black">My wishlist</h3>
            <button class="btn btn-success shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Add item to my wishlist" @click="openWishModal"><Plus class="w-5 h-5" /></button>
          </div>
          <p class="text-sm opacity-70 mt-1">Only you can add or delete your own items.</p>
          <div v-if="!myBoardItems.length" class="text-sm opacity-70 mt-3">No wishes yet — tap + to add your first one.</div>
          <div v-for="it in myBoardItems" :key="it.id" class="wish-card mt-3">
            <div class="flex gap-2">
              <div class="flex-1 min-w-0">
                <p style="white-space:pre-wrap">{{ it.text }}</p>
                <p v-if="it.notes" class="text-sm opacity-70 mt-1" style="white-space:pre-wrap">{{ it.notes }}</p>
                <p v-if="it.reserved" class="text-error font-bold text-sm mt-1">Reserved</p>
              </div>
              <a v-if="it.link" :href="it.link" target="_blank" rel="noopener" class="shrink-0" style="color:#34d399"><ExternalLink class="w-5 h-5" /></a>
              <button class="shrink-0" style="background:none;border:none;color:inherit;cursor:pointer" aria-label="Delete wish" @click="deleteWishItem(it.id)"><Trash2 class="w-5 h-5" /></button>
            </div>
            <img v-if="it.image" :src="it.image" alt="" style="max-width:100%;border-radius:0.75rem;margin-top:8px" />
          </div>
        </div>
      </section>

      <!-- EVERYONE'S WISHLISTS -->
      <section v-show="view === 'wishlists'">
        <div class="card m3-card p-4">
          <div class="flex gap-2 items-start">
            <ScorSanta :size="4" />
            <div class="santa-bubble santa-bubble-sm flex-1"><span class="bubble-dot" aria-hidden="true"></span>Tap a member to see what they want — reserve one so nobody buys the same gift twice.</div>
          </div>
          <h2 class="text-2xl font-black">Everyone's wishlists</h2>
          <input class="input mt-3" v-model="wishSearch" placeholder="Search a name, nickname, or alias…" />
          <div v-if="!filteredWishBoard.length" class="text-sm opacity-70 mt-3">{{ localWishBoard.length ? 'No match.' : 'No members yet.' }}</div>
          <button v-for="o in filteredWishBoard" :key="o.id" class="w-full text-left p-3 mt-2 card" style="border-radius:1rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:inherit;cursor:pointer" @click="openWishOwnerModal(o)">
            <b>{{ o.display }}</b><span v-if="o.nickname"> “{{ o.nickname }}”</span><span v-if="o.aliases && o.aliases.length" class="text-sm opacity-60"> (also: {{ o.aliases.join(', ') }})</span><span v-if="guestMe && o.id === guestMe.id"> — you</span>
            <span class="text-sm opacity-70"> • {{ o.items.length ? o.items.length + ' wish(es)' : 'no wishlist yet' }}</span>
            <ChevronRight class="w-4 h-4 inline float-right" />
          </button>
        </div>
      </section>

      <!-- VOTE -->
      <section v-show="view === 'vote'">
        <div class="card m3-card p-4">
          <div class="flex gap-2 items-start">
            <ScorSanta :size="4" />
            <div class="santa-bubble santa-bubble-sm flex-1"><span class="bubble-dot" aria-hidden="true"></span>Vote on each poll! One vote each per poll, and you can change it until the host closes that poll.</div>
          </div>
          <h2 class="text-2xl font-black">Polls</h2>
          <div v-if="!localPolls.length" class="text-sm opacity-70 mt-3">No polls yet.</div>
          <div v-for="p in localPolls" :key="p.id" class="mt-4 rounded-xl p-3" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)">
            <b>{{ p.question }}</b>
            <p class="text-sm opacity-70">{{ p.open ? 'Voting open' : 'Poll closed' }} • {{ p.total }} vote(s)</p>
            <div v-for="o in p.options" :key="o.id" class="mt-2">
              <button :disabled="!p.open" class="w-full text-left flex items-center gap-2" style="background:none;border:none;color:inherit;cursor:pointer" @click="votePoll(p.id, o.id)">
                <span style="width:18px;height:18px;border-radius:9999px;border:2px solid #34d399;display:inline-flex;align-items:center;justify-content:center"><span v-if="p.my_vote === o.id" style="width:8px;height:8px;border-radius:9999px;background:#34d399"></span></span>
                <span class="flex-1">{{ o.text }}</span>
                <span class="text-sm opacity-70">{{ p.total ? Math.round(o.votes / p.total * 100) : 0 }}% ({{ o.votes }})</span>
              </button>
              <div class="poll-bar mt-1"><div class="poll-bar-fill" :style="{ width: (p.total ? Math.round(o.votes / p.total * 100) : 0) + '%' }"></div></div>
            </div>
            <p v-if="!p.open && p.options.length" class="text-sm mt-2">Winner: <b>{{ p.options.reduce((a, b) => b.votes > a.votes ? b : a).text }}</b></p>
          </div>
        </div>
      </section>

      </main>

    <!-- ADMIN LAYOUT: sidebar + main, desktop-only, signed-in host only -->
    <div v-if="isHostView && hostSignedInLocal" class="admin-layout">
      <main class="admin-main">
      <!-- HOST DASHBOARD: admin overview, rendered only when signed in -->
      <section v-if="view === 'hostdashboard' && hostSignedInLocal">
        <header class="admin-header">
          <div><h1>Dashboard</h1><p class="admin-subtitle">Overview of your party. Recipients are never shown here.</p></div>
          <button v-if="setupDoneLocal" class="btn btn-sm shrink-0" style="width:auto" @click="copyInvite">Copy invite code</button>
        </header>
        <div v-if="!setupDoneLocal" class="admin-panel">
          <p class="text-sm opacity-70">No party set up yet. Create one first — party details and the guest list are saved together.</p>
          <button class="btn btn-primary mt-3" style="width:auto" @click="goTo('hostsetup', '/host/setup')">Create party</button>
        </div>
        <template v-else>
          <div class="admin-stats">
            <div class="admin-stat"><div class="admin-stat-value">{{ hostList.length }}</div><div class="admin-stat-label">Guests</div></div>
            <div class="admin-stat"><div class="admin-stat-value">{{ hostList.filter(g => g.claimed).length }}</div><div class="admin-stat-label">Locked</div></div>
            <div class="admin-stat"><div class="admin-stat-value">{{ hostList.filter(g => !g.claimed).length }}</div><div class="admin-stat-label">Awaiting</div></div>
            <div class="admin-stat"><div class="admin-stat-value">{{ localPolls.length }}</div><div class="admin-stat-label">Polls</div></div>
          </div>
          <div class="poll-bar"><div class="poll-bar-fill" :style="{ width: (hostList.length ? Math.round(hostList.filter(g => g.claimed).length / hostList.length * 100) : 0) + '%' }"></div></div>
          <p class="text-sm opacity-70 mt-1">{{ hostList.filter(g => g.claimed).length }} of {{ hostList.length }} locked</p>
          <div class="admin-grid">
            <div class="admin-grid-main">
              <div class="admin-panel">
                <div class="admin-panel-header"><h2>{{ localParty }}</h2></div>
                <p class="text-sm opacity-70">Date: {{ partyDateLocal || 'Not set' }} • Gift budget: {{ formatBudget(localBudget) }} • Guests: {{ hostList.length }}</p>
                <div class="admin-form-grid mt-4">
                  <div>
                    <h3 class="font-black"><Ticket class="w-4 h-4 inline" /> Invite code</h3>
                    <p class="text-sm opacity-70 mt-1">Guests enter this code to join. No login needed.</p>
                    <p class="text-xl font-black tracking-widest mt-2">{{ inviteCode || '—' }}</p>
                    <div class="flex gap-2 mt-2"><button class="btn btn-sm shrink-0" style="width:auto" @click="copyInvite">Copy code</button><button class="btn btn-sm btn-ghost shrink-0" style="width:auto" @click="regenInvite">New code</button></div>
                  </div>
                  <div v-if="qrUrl">
                    <h3 class="font-black"><QrCode class="w-4 h-4 inline" /> Single QR</h3>
                    <p class="text-sm opacity-70 mt-1">Print this — everyone scans the same one. The invite code is built in.</p>
                    <img :src="qrUrl" alt="Secret Santa QR code" class="mx-auto my-3" style="max-width:180px;width:100%" />
                    <div class="text-center"><button class="btn btn-sm shrink-0" style="width:auto" onclick="window.print()"><Printer class="w-4 h-4 inline" /> Print QR sheet</button></div>
                  </div>
                </div>
              </div>
              <div class="admin-panel">
                <div class="admin-panel-header"><h2>Claimed status</h2><button class="btn btn-sm shrink-0" style="width:auto" @click="refreshClaimed">Refresh</button></div>
                <p class="text-sm opacity-70">Recipients are never shown here — only whether each name is locked.</p>
                <p v-if="!hostList.length" class="text-sm opacity-70 mt-3">No guests yet — add them on the Guests page.</p>
                <table v-else class="admin-table">
                  <thead><tr><th>Name</th><th>Nickname</th><th>Status</th><th>Actions</th></tr></thead>
                  <tbody>
                    <tr v-for="g in hostList" :key="g.id">
                      <td><b>{{ g.display }}</b></td>
                      <td>{{ g.nickname || '—' }}</td>
                      <td><span v-if="g.claimed" class="text-error font-bold">Locked</span><span v-else class="opacity-60">Not yet locked</span></td>
                      <td><button v-if="g.claimed" class="btn btn-sm btn-ghost shrink-0" style="width:auto" @click="resetOne(g.id)">Reset</button><span v-else class="opacity-60">—</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="admin-grid-side">
              <div class="admin-panel">
                <h2>Quick actions</h2>
                <div class="flex flex-col gap-2 mt-3">
                  <button class="btn btn-sm w-full" @click="goTo('hostsetup', '/host/setup')">Party settings</button>
                  <button class="btn btn-sm w-full" @click="goTo('hostguests', '/host/guests')">Manage guests</button>
                  <button class="btn btn-sm w-full" @click="goTo('hostpoll', '/host/poll')">Manage polls</button>
                  <button class="btn btn-sm w-full" @click="copyInvite">Copy invite code</button>
                </div>
              </div>
              <div class="admin-panel">
                <h2>Admin controls</h2>
                <p class="text-sm opacity-70 mt-1">Host only.</p>
                <div class="flex flex-col gap-2 mt-3">
                  <button class="btn btn-sm w-full" @click="hostRedraw">Redraw</button>
                  <button class="btn btn-sm w-full" @click="hostClearWishlists">Clear all wishlists</button>
                  <button class="btn btn-sm w-full" @click="hostUnclaimAll">Unclaim all</button>
                  <button class="btn btn-sm w-full" @click="openChangePasswordModal">Change password</button>
                </div>
              </div>
              <div class="admin-panel admin-danger">
                <h2 class="text-error">Danger zone</h2>
                <p class="text-sm opacity-70 mt-1">Deletes the whole draw and all claims. This cannot be undone.</p>
                <button class="btn btn-sm btn-error mt-3" style="width:auto" @click="resetAll">Reset everything</button>
              </div>
            </div>
          </div>
        </template>
      </section>

<!-- HOST SETUP: Create party (details + guests, one flow) vs Party settings (details only) -->
      <section v-if="view === 'hostsetup' && hostSignedInLocal">
        <header class="admin-header">
          <div><h1>{{ setupDoneLocal ? 'Party settings' : 'Create party' }}</h1><p class="admin-subtitle" v-if="setupDoneLocal">Party details only. Guests are managed on the Guests page — nothing here changes or clears them.</p><p class="admin-subtitle" v-else>Party details and the guest list, saved together.</p></div>
          <button class="btn btn-success btn-sm shrink-0" style="width:auto" :disabled="savingSetup" @click="doSetup"><span v-if="savingSetup">Saving…</span><span v-else><Check class="w-5 h-5 inline" /> Save</span></button>
        </header>
        <div class="admin-panel">
          <div class="admin-form-grid">
            <label class="block">Party name <input class="input" v-model="partyName" /></label>
            <label class="block">Party date <input class="input" type="date" v-model="partyDateLocal" /></label>
            <div class="admin-field-full">
              <p class="block">Gift budget</p>
              <div class="flex flex-wrap gap-2 mt-2 items-center">
                <button class="btn btn-sm" style="width:auto" :class="{ 'btn-primary': partyBudget === '250' }" @click="partyBudget = '250'">₱250</button>
                <button class="btn btn-sm" style="width:auto" :class="{ 'btn-primary': partyBudget === '500' }" @click="partyBudget = '500'">₱500</button>
                <button class="btn btn-sm" style="width:auto" :class="{ 'btn-primary': partyBudget === '1000' }" @click="partyBudget = '1000'">₱1000</button>
                <div class="relative flex-1" style="min-width:120px"><span class="absolute left-3 top-1/2 opacity-70" style="transform:translateY(-50%)">₱</span><input class="input" style="padding-left:28px" v-model="partyBudget" inputmode="numeric" placeholder="Custom amount" /></div>
              </div>
            </div>
          </div>
          <div v-if="!setupDoneLocal" class="mt-6">
            <div class="admin-panel-header"><h2>Guests ({{ guestRows.length }})</h2><button class="btn btn-sm shrink-0" style="width:auto" @click="openGuestModal(-1, 'setup')">+ Add guest</button></div>
            <p class="text-sm opacity-70">Add everyone now — this list is saved with the party, and the draw is made from it. You can change it later on the Guests page, but only before anyone locks in.</p>
            <p v-if="!guestRows.length" class="text-sm opacity-70 mt-3">No guests yet. Tap Add guest to add your first one.</p>
            <table v-else class="admin-table">
              <thead><tr><th>Name</th><th>Nickname</th><th>Aliases</th><th>PIN set</th><th>Actions</th></tr></thead>
              <tbody>
                <tr v-for="(r, i) in guestRows" :key="i">
                  <td><b>{{ r.display }}</b></td>
                  <td>{{ r.nickname || '—' }}</td>
                  <td>{{ r.aliasesText || '—' }}</td>
                  <td>{{ r.pin ? 'Yes' : 'No' }}</td>
                  <td>
                    <button class="btn btn-ghost shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Edit guest" @click="openGuestModal(i, 'setup')"><Pencil class="w-4 h-4" /></button>
                    <button class="btn btn-ghost shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Remove guest" @click="removeGuestRow(i)"><Trash2 class="w-4 h-4" /></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

<!-- HOST POLLS -->
      <section v-if="view === 'hostpoll' && hostSignedInLocal">
        <header class="admin-header">
          <div><h1>Polls</h1><p class="admin-subtitle">Host poll management — all polls, newest first. Guests can vote on open polls; closed polls show their final winner.</p></div>
          <button class="btn btn-sm shrink-0" style="width:auto" :disabled="!setupDoneLocal" @click="openPollModal"><Plus class="w-4 h-4" /> Create poll</button>
        </header>
        <div v-if="!setupDoneLocal" class="admin-panel"><p class="text-sm opacity-70">No party set up yet — create one first, then you can create polls here.</p><button class="btn btn-sm mt-3" style="width:auto" @click="goTo('hostsetup', '/host/setup')">Create party</button></div>
        <div v-else-if="!localPolls.length" class="admin-panel"><p class="text-sm opacity-70">No polls yet — tap Create poll to add your first one.</p></div>
        <div v-else class="admin-panel">
          <table class="admin-table">
            <thead><tr><th>Question</th><th>Status</th><th>Votes</th><th>Winner</th><th>Actions</th></tr></thead>
            <tbody>
              <template v-for="p in localPolls.slice().reverse()" :key="p.id">
                <tr>
                  <td><b>{{ p.question }}</b></td>
                  <td>{{ p.open ? 'Open' : 'Closed' }}</td>
                  <td>{{ p.total }}</td>
                  <td>{{ (!p.open && p.options.length) ? p.options.reduce((a, b) => b.votes > a.votes ? b : a).text : '—' }}</td>
                  <td><button class="btn btn-ghost shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Poll settings" @click="togglePollMenu(p.id)"><Settings2 class="w-5 h-5" /></button></td>
                </tr>
                <tr v-if="pollMenuId === p.id">
                  <td colspan="5">
                    <div class="flex flex-wrap gap-2">
                      <button class="btn btn-sm shrink-0" style="width:auto" @click="openEditPollModal(p)"><Pencil class="w-4 h-4" /> Edit</button>
                      <button class="btn btn-sm shrink-0" style="width:auto" @click="setPollOpen(p.id, !p.open)">{{ p.open ? 'Close' : 'Reopen' }}</button>
                      <button class="btn btn-sm btn-error shrink-0" style="width:auto" @click="resetPoll(p)">Delete</button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>

<!-- HOST GUESTS: real saved-party guests, not the setup draft -->
      <section v-if="view === 'hostguests' && hostSignedInLocal">
        <header class="admin-header">
          <div><h1>Guests ({{ hostList.length }})</h1><p class="admin-subtitle">The saved party's guests. Adding, editing or removing a guest re-draws the Secret Santa automatically — recipients are never shown.</p></div>
          <button class="btn btn-sm shrink-0" style="width:auto" :disabled="hostList.some(g => g.claimed)" @click="openGuestModal(-1, 'saved')">+ Add guest</button>
        </header>
        <div v-if="!setupDoneLocal" class="admin-panel"><p class="text-sm opacity-70">No party set up yet — create one first.</p><button class="btn btn-sm mt-3" style="width:auto" @click="goTo('hostsetup', '/host/setup')">Create party</button></div>
        <template v-else>
          <p v-if="hostList.some(g => g.claimed)" class="admin-banner">Guest list is locked: at least one name is locked in. Reset claims first (dashboard, or Reset on a locked name below) before adding, editing or removing guests.</p>
          <p v-if="!hostList.length" class="admin-panel text-sm opacity-70">No guests yet. Tap Add guest to add your first one.</p>
          <div v-else class="admin-panel">
            <input class="input mb-3" v-model="hostGuestSearch" placeholder="Search a name, nickname, or alias…" />
            <p v-if="hostGuestSearch && !filteredHostList.length" class="text-sm opacity-70 mb-3">No match.</p>
            <table class="admin-table">
              <thead><tr><th>Name</th><th>Nickname</th><th>Aliases</th><th>PIN set</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                <tr v-for="g in filteredHostList" :key="g.id">
                  <td><b>{{ g.display }}</b></td>
                  <td>{{ g.nickname || '—' }}</td>
                  <td>{{ (g.aliases && g.aliases.length) ? g.aliases.join(', ') : '—' }}</td>
                  <td>{{ g.pin_set ? 'Yes' : 'No' }}</td>
                  <td><span v-if="g.claimed" class="text-error font-bold">Locked</span><span v-else class="opacity-60">Not yet</span></td>
                  <td>
                    <button v-if="g.claimed" class="btn btn-sm btn-ghost shrink-0" style="width:auto" @click="resetOne(g.id)">Reset</button>
                    <button class="btn btn-ghost shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Edit guest" :disabled="hostList.some(x => x.claimed)" @click="openGuestModal(hostList.indexOf(g), 'saved')"><Pencil class="w-4 h-4" /></button>
                    <button class="btn btn-ghost shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Remove guest" :disabled="hostList.some(x => x.claimed)" @click="removeSavedGuest(g)"><Trash2 class="w-4 h-4" /></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </section>

      </main>
    </div>

    <!-- Guest dock -->
    <nav v-if="guestMe && isGuestView" class="dock" aria-label="Guest navigation">
      <button class="dock-item" :class="{ 'dock-item-active': view === 'guesthome' }" @click="goToGuestHome"><Home class="w-6 h-6" /><span>Home</span></button>
      <button class="dock-item" :class="{ 'dock-item-active': view === 'gifts' }" @click="goToGifts"><ShoppingBag class="w-6 h-6" /><span>Gifts</span></button>
      <button class="dock-item" @click="openWishModal" aria-label="Create a wishlist"><span class="dock-plus"><StarPlus class="w-7 h-7" /></span></button>
      <button class="dock-item" :class="{ 'dock-item-active': view === 'wishlists' }" @click="goToWishlists"><Users class="w-6 h-6" /><span>Wishlists</span></button>
      <button class="dock-item" :class="{ 'dock-item-active': view === 'vote' }" @click="goToVote"><Vote class="w-6 h-6" /><span>Vote</span></button>
    </nav>

    <!-- FAB (home only, guest links only — no host link) -->
    <div v-if="view === 'home' && fabOpen" class="fab-menu">
      <button class="fab-item" @click="fabGo('home')"><Home class="w-6 h-6" /><span>Home</span></button>
      <button class="fab-item" :disabled="!setupDoneLocal" @click="fabGo('guest')"><Gift class="w-6 h-6" /><span>Guest</span></button>
      <button class="fab-item" :disabled="!setupDoneLocal" @click="fabGo('gifts')"><ShoppingBag class="w-6 h-6" /><span>Gifts</span></button>
      <button class="fab-item" :disabled="!setupDoneLocal" @click="fabGo('vote')"><Vote class="w-6 h-6" /><span>Vote</span></button>
    </div>
    <div v-if="view === 'home'" class="fab-wrap"><button class="fab-btn" @click="fabOpen = !fabOpen">{{ fabOpen ? '×' : '+' }}</button></div>

    <!-- Toast -->
    <div v-if="toastMsg" class="fixed left-1/2 px-4 py-3 rounded-2xl shadow-lg flex items-center gap-3" style="top:calc(12px + env(safe-area-inset-top));transform:translateX(-50%);width:min(92vw,480px);z-index:60;background:rgba(24,24,27,0.92);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1);color:#fafafa">
      <span class="shrink-0" style="width:24px;height:24px;border-radius:9999px;display:flex;align-items:center;justify-content:center" :style="{ background: toastOk ? '#34d399' : '#ff3954' }"><Check v-if="toastOk" class="w-4 h-4" style="color:#18181b" /><X v-else class="w-4 h-4" style="color:#fff" /></span>
      <span class="text-sm font-bold flex-1">{{ toastMsg }}</span>
    </div>

    <!-- Is this you? -->
    <div v-if="showConfirmModal" class="modal-backdrop" @click.self="closeConfirmModal">
      <div class="modal-card" style="min-height:45vh;display:flex;flex-direction:column">
        <h2 class="text-2xl font-black">Is this you?</h2>
        <p class="text-3xl font-black mt-8 text-center">{{ selected ? selected.display : '' }}<span v-if="selected && selected.nickname"> “{{ selected.nickname }}”</span></p>
        <p v-if="selected && selected.aliases.length" class="text-sm opacity-60 text-center">Also: {{ selected.aliases.join(', ') }}</p>
        <p v-if="selected && selected.claimed" class="alert alert-warning mt-3" style="border-radius:1rem">This name is already locked, so no recipient will be shown again here. You can still do your wishlist and vote.</p>
        <p v-if="selected && !selected.claimed" class="text-sm opacity-70 mt-3">Confirming locks this name to you — only the host can unlock it.</p>
        <div v-if="selected && selected.needs_pin" class="mt-3"><p class="text-sm font-bold mb-1">Your 4-digit code <span class="opacity-60 font-normal">— required to see your recipient</span></p><InputOtp v-model="guestPin" type="password" /></div>
        <div class="flex gap-2 mt-auto pt-6">
          <button class="btn btn-ghost flex-1" @click="closeConfirmModal">No, go back</button>
          <button v-if="selected && !selected.claimed" class="btn btn-success flex-1" @click="confirmEnter">Yes, that's me</button>
          <button v-if="selected && selected.claimed" class="btn btn-success flex-1" @click="confirmClaimedGuest">Yes, that's me</button>
        </div>
      </div>
    </div>

    <!-- Host sign-in: username/password, NOT a PIN, NOT email -->
    <div v-if="showHostEntryModal" class="modal-backdrop" @click.self="closeHostEntryModal">
      <div class="modal-card">
        <div class="flex items-center justify-between"><h3 class="text-xl font-black">Host sign-in</h3><button class="btn btn-ghost btn-sm" style="width:auto" @click="closeHostEntryModal">×</button></div>
        <p class="text-sm opacity-70 mt-1">Sign in to manage your party.</p>
        <label class="block mt-3">Username <input class="input" v-model="hostUser" @keyup.enter="submitHostLogin" /></label>
        <label class="block mt-2">Password <input class="input" type="password" v-model="hostPass" @keyup.enter="submitHostLogin" /></label>
        <p v-if="hostLoginError" class="text-sm text-error mt-2">Incorrect username or password — try again.</p>
        <button class="btn btn-primary w-full mt-4" @click="submitHostLogin"><KeyRound class="w-5 h-5" /> Sign in</button>
        <button class="btn btn-ghost w-full mt-2" @click="closeHostEntryModal">Cancel</button>
      </div>
    </div>

    <!-- Change host password -->
    <div v-if="showChangePasswordModal" class="modal-backdrop" @click.self="closeChangePasswordModal">
      <div class="modal-card">
        <div class="flex items-center justify-between"><h3 class="text-xl font-black">Change password</h3><button class="btn btn-ghost btn-sm" style="width:auto" @click="closeChangePasswordModal">×</button></div>
        <label class="block mt-3">Current password <input class="input" type="password" v-model="changePassCurrent" /></label>
        <label class="block mt-2">New password <input class="input" type="password" v-model="changePassNew" /></label>
        <label class="block mt-2">Confirm new password <input class="input" type="password" v-model="changePassConfirm" /></label>
        <p v-if="changePassMsg" class="text-sm text-error mt-2">{{ changePassMsg }}</p>
        <button class="btn btn-success w-full mt-3" :disabled="changingPassword" @click="doChangePassword">{{ changingPassword ? 'Changing…' : 'Change password' }}</button>
        <button class="btn btn-ghost w-full mt-2" @click="closeChangePasswordModal">Cancel</button>
      </div>
    </div>

    <!-- Add/Edit guest -->
    <div v-if="showGuestModal" class="modal-backdrop" @click.self="closeGuestModal">
      <div class="modal-card">
        <h3 class="text-xl font-black">{{ guestEditIndex >= 0 ? 'Edit guest' : 'Add guest' }}</h3>
        <label class="block mt-3">Name <input class="input" v-model="guestForm.display" /></label>
        <label class="block mt-2">Nickname <input class="input" v-model="guestForm.nickname" /></label>
        <label class="block mt-2">Aliases (comma-separated) <input class="input" v-model="guestForm.aliasesText" /></label>
        <label class="block mt-2">Guest PIN (4 digits, required for new guests — leave blank on edit to keep the existing code) <InputOtp v-model="guestForm.pin" type="password" /></label>
        <div class="flex gap-2 mt-4"><button class="btn btn-ghost flex-1" @click="closeGuestModal">Cancel</button><button class="btn btn-success flex-1" @click="saveGuestModal">Save</button></div>
      </div>
    </div>

    <!-- Wishlist form -->
    <div v-if="showWishModal" class="modal-backdrop" @click.self="closeWishModal">
      <div class="modal-card">
        <h3 class="text-xl font-black">Add to my wishlist</h3>
        <label class="block mt-3">Wish <input class="input" v-model="wishText" /></label>
        <label class="block mt-2">Link <input class="input" v-model="wishLink" placeholder="https://…" /></label>
        <div class="block mt-2"><span class="block mb-1">Screenshot</span><label class="file-btn"><Upload class="w-5 h-5 shrink-0" /><span class="file-name">{{ wishImageName || 'Choose a screenshot' }}</span><span v-if="!wishImageName" class="file-hint">No file chosen</span><input type="file" accept="image/*" class="file-input" @change="onWishImageFile" /></label></div>
        <img v-if="wishImage" :src="wishImage" alt="" style="max-width:100%;border-radius:0.75rem;margin-top:8px" />
        <label class="block mt-2">Notes <textarea class="input" v-model="wishNotes" placeholder="Where to buy it — shop, directions, size / colour (optional)"></textarea></label>
        <div class="flex gap-2 mt-4"><button class="btn btn-ghost flex-1" @click="closeWishModal"><X class="w-5 h-5" /> Cancel</button><button class="btn btn-success flex-1" @click="addWishItem"><Plus class="w-5 h-5" /> Add</button></div>
      </div>
    </div>

    <!-- Member wishlist -->
    <div v-if="showWishOwnerModal && wishModalOwner" class="modal-backdrop" @click.self="closeWishOwnerModal">
      <div class="modal-card">
        <div class="flex items-center justify-between"><h3 class="text-xl font-black">{{ wishModalOwner.display }}'s wishlist</h3><button class="btn btn-ghost btn-sm" style="width:auto" @click="closeWishOwnerModal">×</button></div>
        <div v-if="!wishModalOwner.items.length" class="text-sm opacity-70 mt-3">No wishes yet.</div>
        <div v-for="it in wishModalOwner.items" :key="it.id" class="wish-card mt-3">
          <div class="flex gap-2"><div class="flex-1"><p style="white-space:pre-wrap">{{ it.text }}</p><p v-if="it.notes" class="text-sm opacity-70 mt-1" style="white-space:pre-wrap">{{ it.notes }}</p><p v-if="it.reserved" class="text-error font-bold text-sm mt-1">Reserved</p></div>
          <a v-if="it.link" :href="it.link" target="_blank" rel="noopener" style="color:#34d399"><ExternalLink class="w-5 h-5" /></a></div>
          <img v-if="it.image" :src="it.image" alt="" style="max-width:100%;border-radius:0.75rem;margin-top:8px" />
          <button v-if="guestMe && wishModalOwner.id !== guestMe.id" class="btn btn-sm mt-2" style="width:auto" :disabled="it.reserved && !it.reserved_by_me" @click="reserveWishItem(wishModalOwner.id, it.id)">{{ it.reserved_by_me ? 'Unreserve' : it.reserved ? 'Reserved' : 'Reserve' }}</button>
        </div>
      </div>
    </div>

    <!-- Create poll -->
    <div v-if="showPollModal" class="modal-backdrop" @click.self="closePollModal">
      <div class="modal-card">
        <div class="flex items-center justify-between"><h3 class="text-xl font-black">Create a poll</h3><button class="btn btn-ghost btn-sm" style="width:auto" @click="closePollModal">×</button></div>
        <label class="block mt-3">Question <input class="input" v-model="pollQuestion" /></label>
        <label class="block mt-2">Options (one per line) <textarea class="input" v-model="pollOptionsText" rows="4"></textarea></label>
        <div class="flex gap-2 mt-4"><button class="btn btn-ghost flex-1" @click="closePollModal">Cancel</button><button class="btn btn-success flex-1" @click="setupPoll">Create</button></div>
      </div>
    </div>

    <!-- Edit poll -->
    <div v-if="showEditPollModal" class="modal-backdrop" @click.self="closeEditPollModal">
      <div class="modal-card">
        <h3 class="text-xl font-black">Edit poll</h3>
        <label class="block mt-3">Question <input class="input" v-model="editPollQuestion" /></label>
        <label class="block mt-2">Options (one per line) <textarea class="input" v-model="editPollOptionsText" rows="4"></textarea></label>
        <div class="flex gap-2 mt-4"><button class="btn btn-ghost flex-1" @click="closeEditPollModal">Cancel</button><button class="btn btn-success flex-1" @click="doEditPoll">Save changes</button></div>
      </div>
    </div>

    <!-- Generic confirm -->
    <div v-if="showConfirmDialog" class="modal-backdrop" @click.self="closeConfirmDialog">
      <div class="modal-card">
        <h3 class="text-xl font-black">{{ confirmDialogTitle }}</h3>
        <p class="text-sm opacity-70 mt-2">{{ confirmDialogMessage }}</p>
        <div class="flex gap-2 mt-4"><button class="btn btn-ghost flex-1" @click="closeConfirmDialog">Cancel</button><button class="btn btn-error flex-1" @click="confirmDialogOk">{{ confirmDialogOkText }}</button></div>
      </div>
    </div>

    <!-- Guest entry -->
    <div v-if="showGuestEntryModal" class="modal-backdrop" @click.self="closeGuestEntryModal">
      <div class="modal-card">
        <div class="flex items-center justify-between"><h3 class="text-xl font-black">Join as a guest</h3><button class="btn btn-ghost btn-sm" style="width:auto" @click="closeGuestEntryModal">×</button></div>
        <p class="text-sm opacity-70 mt-1">Scan the host's QR code, or enter the invite code from the host.</p>
        <button class="btn btn-primary w-full mt-4" @click="showGuestEntryModal = false; openQrModal()"><QrCode class="w-5 h-5" /> Scan QR code</button>
        <p class="text-sm opacity-70 text-center mt-4">or</p>
        <div class="flex gap-2 mt-2"><input class="input" v-model="guestEntryCode" placeholder="Invite code" @keyup.enter="enterGuestWithCode" /><button class="btn btn-primary shrink-0" style="width:auto" :disabled="!guestEntryCode.trim()" @click="enterGuestWithCode">Continue <ArrowRight class="w-4 h-4" /></button></div>
        <button class="btn btn-ghost w-full mt-2" @click="closeGuestEntryModal">Cancel</button>
      </div>
    </div>

    <!-- QR (design-only, camera never starts — same as the old app) -->
    <div v-if="showQrModal" class="modal-backdrop" @click.self="closeQrModal">
      <div class="modal-card text-center">
        <h3 class="text-xl font-black">Scan QR code</h3>
        <div class="mx-auto mt-4" style="width:200px;height:200px;border:2px dashed rgba(255,255,255,0.3);border-radius:1rem;display:flex;align-items:center;justify-content:center"><QrCode class="w-12 h-12 opacity-50" /></div>
        <p class="text-sm opacity-70 mt-3">Point your phone camera at the host's QR code, or open the direct link they sent you.</p>
        <button class="btn btn-primary w-full mt-3" @click="openGuestTemp">Continue to guest page</button>
        <button class="btn btn-ghost w-full mt-2" @click="closeQrModal">Cancel</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import InputOtp from '../Components/InputOtp.vue';
import ScorSanta from '../Components/ScorSanta.vue';
import {
  ArrowRight, Check, ChevronRight, ExternalLink, Gift, Home, KeyRound,
  LayoutDashboard, LogOut, Pencil, Plus, Printer, QrCode, Search, Settings,
  Settings2, ShoppingBag, StarPlus, Ticket, Trash2, Upload, Users,
  Vote, X,
} from '@lucide/vue';

function norm(s) {
  return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
}
function lev(a, b) {
  const m = a.length, n = b.length;
  if (!m) return n;
  if (!n) return m;
  let prev = Array.from({ length: n + 1 }, (_, i) => i), cur = new Array(n + 1);
  for (let i = 1; i <= m; i++) {
    cur[0] = i;
    for (let j = 1; j <= n; j++) cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
    [prev, cur] = [cur, prev];
  }
  return prev[n];
}
function searchable(g) { return norm([g.display, g.nickname, (g.aliases || []).join(' ')].join(' ')); }
function matches(g, q) {
  if (!q) return true;
  const hay = searchable(g).split(' ');
  return q.split(' ').every((tok) => hay.some((h) => h.includes(tok) || (tok.length >= 3 && lev(tok, h) <= 1)));
}

export default {
  components: {
    InputOtp, ScorSanta, ArrowRight, Check, ChevronRight, ExternalLink, Gift, Home, KeyRound,
    LayoutDashboard, LogOut, Pencil, Plus, Printer, QrCode, Search, Settings,
    Settings2, ShoppingBag, StarPlus, Ticket, Trash2, Upload, Users, Vote, X,
  },
  props: {
    initialView: { type: String, default: 'home' },
    setupDone: { type: Boolean, default: false },
    party: { type: String, default: 'Christmas Party' },
    budget: { type: String, default: '' },
    partyDate: { type: String, default: '' },
    guests: { type: Array, default: () => [] },
    wishBoard: { type: Array, default: () => [] },
    polls: { type: Array, default: () => [] },
    sessionGuest: { type: Object, default: null },
    sessionGuestPin: { type: String, default: '' },
    hostSignedIn: { type: Boolean, default: false },
    hostUsername: { type: String, default: '' },
  },
  data() {
    return {
      localGuests: this.guests, localWishBoard: this.wishBoard, localPolls: this.polls,
      localParty: this.party, localBudget: this.budget, setupDoneLocal: this.setupDone,
      view: this.initialView,
      fabOpen: false, santaWaving: false,
      search: '', guestSearched: false, selected: null, showConfirmModal: false,
      showConfirmDialog: false,
 confirmDialogTitle: '', confirmDialogMessage: '',
      confirmDialogOkText: 'OK', confirmDialogCallback: null,
      guestPin: '', guestMe: null, guestRecipient: null, guestAlreadyClaimed: false,
      wishModalOwner: null, showWishOwnerModal: false, pendingWishOwnerId: null,
      partyName: this.party, partyBudget: this.budget, partyDateLocal: this.partyDate,
      guestRows: [], showGuestModal: false, guestEditIndex: -1,
      guestForm: { id: '', display: '', nickname: '', aliasesText: '', pin: '' },
      guestModalContext: 'saved',
      savingSetup: false, toastMsg: '', toastOk: true, toastTimer: null,
      hostMsg: '', hostOk: false, hostList: [], hostGuestSearch: '', hostLoaded: false, inviteCode: '',
      showHostEntryModal: false, hostUser: '', hostPass: '', hostLoginError: false,
      hostSignedInLocal: this.hostSignedIn, hostUsernameLocal: this.hostUsername,
      showChangePasswordModal: false, changePassCurrent: '', changePassNew: '', changePassConfirm: '', changePassMsg: '', changingPassword: false,
      wishMe: null, wishSearch: '', wishText: '', wishLink: '', wishNotes: '', wishPin: '',
      wishImage: '', wishImageName: '', showWishModal: false,
      pollQuestion: 'What should our Christmas theme be?', pollOptionsText: 'Ugly Sweater\nWinter Wonderland\nPajama Party\nRed & Gold',
      showPollModal: false, pollMenuId: null, showEditPollModal: false, editPollId: '', editPollQuestion: '', editPollOptionsText: '',
      showGuestEntryModal: false, guestEntryCode: '', showQrModal: false,
      snow: Array.from({ length: 28 }, (_, i) => ({ left: (4 + (i * 37) % 92) + '%', top: (6 + (i * 29) % 84) + '%', size: (11 + (i * 7) % 12) + 'px', opacity: 0.2 + ((i * 13) % 30) / 100 })),
    };
  },
  computed: {
    isHostView() { return ['host', 'hostdashboard', 'hostguests', 'hostpoll', 'hostsetup'].includes(this.view); },
    isGuestView() { return ['who', 'reveal', 'gifts', 'wishlists', 'vote', 'guesthome'].includes(this.view); },
    filteredGuests() { return this.localGuests.filter((g) => matches(g, norm(this.search))); },
    filteredHostList() { return this.hostList.filter((g) => matches(g, norm(this.hostGuestSearch))); },
    filteredWishBoard() { return this.localWishBoard.filter((o) => matches(o, norm(this.wishSearch))); },
    myBoardItems() {
      if (!this.guestMe) return [];
      const o = this.localWishBoard.find((x) => x.id === this.guestMe.id);
      return o ? o.items : [];
    },
    guestUrl() { return window.location.origin + (this.inviteCode ? '/?guest=1&code=' + encodeURIComponent(this.inviteCode) : '/?guest=1'); },
    qrUrl() { return this.setupDoneLocal ? 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(this.guestUrl) : ''; },
  },
  mounted() {
    this.setupDoneLocal = this.setupDone;
    this.partyName = this.localParty;
    this.partyBudget = this.localBudget;
    // Old hash URLs (#/host/dashboard) redirect to the real path.
    if (window.location.hash.startsWith('#/')) {
      const path = window.location.hash.slice(1);
      if (path !== window.location.pathname) window.location.replace(path + window.location.search);
    }
    if (this.sessionGuest) {
      this.guestMe = this.sessionGuest;
      this.guestPin = this.sessionGuestPin || '';
      this.guestAlreadyClaimed = !!this.sessionGuest.claimed;
    }
    const qs = new URLSearchParams(window.location.search);
    if (qs.get('guest') === '1') {
      const code = qs.get('code');
      if (code) {
        this.api('invite_check', { code }).then(() => this.startGuest(true)).catch(() => this.startGuest());
      } else this.startGuest();
    }
    if (this.isHostView && this.hostSignedInLocal) {
      this.loadHost();
      if (this.view === 'hostpoll') this.loadPoll();
    }
    window.addEventListener('popstate', this.onPopState);
  },
  beforeUnmount() { window.removeEventListener('popstate', this.onPopState); },
  methods: {
    onPopState() {
      const map = { '/': 'home', '/who': 'who', '/reveal': 'reveal', '/gifts': 'gifts', '/wishlists': 'wishlists', '/vote': 'vote', '/guest-home': 'guesthome', '/host/dashboard': 'hostdashboard', '/host/setup': 'hostsetup', '/host/guests': 'hostguests', '/host/poll': 'hostpoll' };
      this.view = map[window.location.pathname] || 'home';
      // Host pages never render content when signed out: the sign-in
      // screen (template) shows instead, and no host data is loaded.
      if (this.isHostView && this.hostSignedInLocal) {
        this.loadHost();
        if (this.view === 'hostpoll') this.loadPoll();
      }
    },
    async api(action, body = {}, method = 'post') {
      try {
        const res = method === 'get' ? await axios.get('/api/' + action, { params: body }) : await axios.post('/api/' + action, body);
        return res.data;
      } catch (e) {
        const msg = e.response?.data?.error || e.message || 'Request failed';
        const err = new Error(msg);
        err.status = e.response?.status;
        throw err;
      }
    },
    showToast(msg, ok = true) {
      this.toastMsg = msg; this.toastOk = ok;
      if (this.toastTimer) clearTimeout(this.toastTimer);
      this.toastTimer = setTimeout(() => { this.toastMsg = ''; }, 3000);
    },
    formatBudget(b) { if (!b) return 'Not set'; const s = String(b).trim(); if (/^[₱$£€]/.test(s)) return s; return '₱' + s; },
    goHome() { this.goTo('home', '/'); },
    goTo(v, path) {
      this.view = v;
      if (path && path !== window.location.pathname) window.history.pushState({}, '', path);
      this.showWishModal = false; this.showQrModal = false; this.showConfirmModal = false; this.showConfirmDialog = false;
      this.showGuestEntryModal = false; this.showHostEntryModal = false; this.showChangePasswordModal = false;
      this.showGuestModal = false; this.showWishOwnerModal = false; this.showPollModal = false; this.pollMenuId = null; this.showEditPollModal = false; this.fabOpen = false;
      if (this.isHostView && this.hostSignedInLocal) {
        this.loadHost();
        if (v === 'hostpoll') this.loadPoll();
      }
    },
    async loadGuests() { try { const d = await this.api('list', {}, 'get'); this.localGuests = d.guests; this.localParty = d.party; this.localBudget = d.budget; this.partyName = d.party; this.partyBudget = d.budget; } catch (e) { if (e.message === 'not_set_up') { this.localGuests = []; } else { throw e; } } },
    async loadWishlists() { try { const d = await this.api('wishlist_list', { me: this.guestMe ? this.guestMe.id : '' }, 'get'); this.localWishBoard = d.board; } catch (e) { if (e.message === 'not_set_up') { this.localWishBoard = []; } else { throw e; } } },
    async loadPoll() { try { const d = await this.api('poll_get', { me: this.guestMe ? this.guestMe.id : '' }, 'get'); this.localPolls = d.polls; } catch (e) { if (e.message === 'not_set_up') { this.localPolls = []; } else { throw e; } } },
    startGuest(bypassDialog = false) {
      if (!this.setupDoneLocal) { this.goHome(); return; }
      if (this.guestMe && (this.guestRecipient || this.guestAlreadyClaimed)) { this.goToGuestHome(); return; }
      if (this.guestMe) { this.goTo('reveal', '/reveal'); return; }
      if (bypassDialog) { this.goTo('who', '/who'); return; }
      this.showGuestEntryModal = true;
    },
    async enterGuestWithCode() {
      try {
        await this.api('invite_check', { code: this.guestEntryCode });
        this.showGuestEntryModal = false; this.goTo('who', '/who');
      } catch (e) { this.showToast(e.message === 'invite_incorrect' ? 'That invite code is incorrect. Ask the host for the right one.' : 'Could not check that code: ' + e.message, false); }
    },
    openQrModal() { this.showQrModal = true; },
    closeQrModal() { this.showQrModal = false; },
    openGuestTemp() { this.closeQrModal(); this.goTo('who', '/who'); },
    waveSanta() { this.santaWaving = true; setTimeout(() => { this.santaWaving = false; }, 1200); },
    fabGo(dest) {
      this.fabOpen = false;
      if (dest === 'home') this.goHome();
      else if (dest === 'guest') this.startGuest();
      else if (dest === 'gifts') this.openGiftsPage();
      else if (dest === 'vote') this.openVotePage();
    },
    async guestLogout() {
      try { await this.api('guest_logout', {}); } catch (e) { /* local logout still proceeds */ }
      this.guestMe = null; this.guestRecipient = null; this.guestAlreadyClaimed = false; this.guestPin = ''; this.wishMe = null; this.wishPin = ''; this.search = ''; this.guestSearched = false; this.selected = null; this.goHome();
    },
    selectGuest(g) {
      this.selected = g; this.guestMe = g;
      this.guestAlreadyClaimed = !!g.claimed;
      this.showConfirmModal = true;
    },
    closeConfirmModal() { this.showConfirmModal = false; this.selected = null; },
    confirmClaimedGuest() { this.showConfirmModal = false; this.goToGuestHome(); },
    async confirmEnter() {
      if (!this.selected) return;
      if (this.selected.claimed) { this.confirmClaimedGuest(); return; }
      const id = this.selected.id;
      this.showConfirmModal = false;
      try {
        const res = await this.api('enter', { id, pin: this.guestPin });
        this.guestRecipient = { name: res.recipient, nickname: res.recipient_nickname, budget: res.budget };
        this.selected = null; await this.loadGuests();
        this.goTo('reveal', '/reveal');
      } catch (e) {
        if (e.message === 'already_claimed') {
          this.guestAlreadyClaimed = true; this.showToast('This name is already locked — no recipient is shown again. Ask the host to reset it.', false); this.goToGuestHome();
        } else if (e.message === 'pin_incorrect') {
          this.showToast('That PIN is incorrect. This name has NOT been locked yet, try again or ask the host.', false); this.showConfirmModal = true;
        } else if (e.message === 'pin_required') {
          this.showToast('This name has no 4-digit code yet — ask the host to set one for you.', false); this.showConfirmModal = true;
        } else { this.showToast('Could not enter: ' + e.message, false); this.showConfirmModal = true; }
      }
    },
    async doReveal() {
      const id = this.selected ? this.selected.id : (this.guestMe ? this.guestMe.id : null);
      if (!id) return;
      try {
        const res = await this.api('reveal', { id, pin: this.guestPin });
        this.guestRecipient = { name: res.recipient, nickname: res.recipient_nickname, budget: res.budget };
        this.selected = null; await this.loadGuests();
      } catch (e) {
        const msg = e.message === 'already_claimed' ? 'This name is already locked — no recipient is shown again. Ask the host if you lost it.' : e.message === 'pin_incorrect' ? 'That PIN is incorrect. This name has NOT been locked yet, try again or ask the host.' : e.message === 'pin_required' ? 'This name has no 4-digit code yet — ask the host to set one for you.' : 'Could not reveal: ' + e.message;
        if (e.message === 'already_claimed') this.guestAlreadyClaimed = true;
        this.showToast(msg, false);
      }
    },
    async goToGuestHome() { await this.loadGuests(); this.goTo('guesthome', '/guest-home'); },
    async goToGifts() { this.wishMe = this.guestMe; this.wishPin = this.guestPin; await this.loadWishlists(); this.goTo('gifts', '/gifts'); },
    async goToWishlists() { this.wishMe = this.guestMe; this.wishPin = this.guestPin; await this.loadWishlists(); this.goTo('wishlists', '/wishlists'); },
    async goToVote() { await this.loadPoll(); this.goTo('vote', '/vote'); },
    openGiftsPage() { if (this.guestMe) this.goToGifts(); else this.startGuest(); },
    openVotePage() { if (this.guestMe) this.goToVote(); else this.startGuest(); },
    openWishModal() { if (this.guestMe) { this.wishMe = this.guestMe; this.wishPin = this.guestPin; } this.showWishModal = true; },
    closeWishModal() { this.showWishModal = false; },
    openWishOwnerModal(owner) { this.wishModalOwner = owner; this.showWishOwnerModal = true; },
    closeWishOwnerModal() { this.showWishOwnerModal = false; },
    async openRecipientWishlist() {
      await this.loadWishlists();
      const o = this.guestRecipient ? this.localWishBoard.find((x) => x.display === this.guestRecipient.name && (x.nickname || '') === (this.guestRecipient.nickname || '')) : null;
      if (o) { this.wishModalOwner = o; this.showWishOwnerModal = true; }
      this.goTo('wishlists', '/wishlists');
      if (o) this.showWishOwnerModal = true;
    },
    async onWishImageFile(e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;
      if (file.size > 10 * 1024 * 1024) { this.showToast('Screenshot is too large — keep it under 10 MB', false); return; }
      const dataUrl = await new Promise((resolve, reject) => { const r = new FileReader(); r.onload = () => resolve(r.result); r.onerror = reject; r.readAsDataURL(file); });
      this.wishImage = dataUrl; this.wishImageName = file.name;
      try {
        const img = new Image();
        await new Promise((resolve, reject) => { img.onload = resolve; img.onerror = reject; img.src = dataUrl; });
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
        canvas.getContext('2d').drawImage(img, 0, 0);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/avif', 0.8));
        if (blob) {
          const avifUrl = await new Promise((resolve) => { const r = new FileReader(); r.onload = () => resolve(r.result); r.readAsDataURL(blob); });
          if (avifUrl.length < dataUrl.length) this.wishImage = avifUrl;
        }
      } catch (err) { /* keep original upload */ }
    },
    async addWishItem() {
      const kind = this.wishImage ? 'image' : (this.wishLink ? 'link' : 'text');
      try {
        await this.api('wishlist_add', { guest_id: this.wishMe.id, pin: this.wishPin, kind, text: this.wishText, link: this.wishLink, image: this.wishImage, notes: this.wishNotes });
        this.showToast('Wish added.');
        this.wishText = ''; this.wishLink = ''; this.wishNotes = ''; this.wishImage = ''; this.wishImageName = ''; this.showWishModal = false;
        await this.loadWishlists();
      } catch (e) { this.showToast(e.message === 'pin_incorrect' ? 'PIN incorrect.' : 'Could not add: ' + e.message, false); }
    },
    async deleteWishItem(itemId) {
      try { await this.api('wishlist_delete', { guest_id: this.wishMe.id, pin: this.wishPin, item_id: itemId }); await this.loadWishlists(); this.showToast('Wishlist item deleted.'); }
      catch (e) { this.showToast('Could not delete: ' + e.message, false); }
    },
    async reserveWishItem(ownerId, itemId) {
      try {
        await this.api('wishlist_reserve', { guest_id: this.guestMe.id, pin: this.guestPin, owner_id: ownerId, item_id: itemId });
        await this.loadWishlists();
        const o = this.localWishBoard.find((x) => x.id === ownerId);
        if (o) this.wishModalOwner = o;
        this.showToast('Reservation updated.');
      } catch (e) { this.showToast('Could not reserve: ' + e.message, false); }
    },
    async votePoll(pollId, optionId) {
      try { const d = await this.api('poll_vote', { poll_id: pollId, option_id: optionId, guest_id: this.guestMe.id, pin: this.guestPin }); this.localPolls = d.polls; this.showToast('Vote recorded.'); }
      catch (e) { this.showToast('Could not vote: ' + e.message, false); }
    },

    /* ---------- host ---------- */
    startHost() { this.showHostEntryModal = true; },
    closeHostEntryModal() { this.showHostEntryModal = false; },
    async submitHostLogin() {
      try {
        const res = await this.api('host_login', { username: this.hostUser, password: this.hostPass });
        this.hostSignedInLocal = true; this.hostUsernameLocal = res.username || this.hostUser;
        this.hostLoginError = false; this.hostPass = ''; this.showHostEntryModal = false;
        await this.loadHost();
        // Stay on the intended host page — its content now renders,
        // replacing the sign-in screen. No navigation away.
        this.showToast('Signed in as host.');
      } catch (err) {
        this.hostLoginError = true;
        this.showToast('Host sign-in failed: ' + err.message, false);
      }
    },
    async hostSignOut() {
      try { await this.api('host_logout', {}); } catch (err) { /* local sign-out still proceeds */ }
      this.hostSignedInLocal = false; this.hostUsernameLocal = ''; this.hostLoaded = false; this.hostList = []; this.hostUser = ''; this.hostPass = ''; this.goHome(); },
    async openDashboard() { await this.loadHost(); this.goTo('hostdashboard', '/host/dashboard'); },
    async loadHost() {
      try {
        const res = await this.api('host_status', {});
        if (res.exists === false) { this.hostLoaded = false; this.hostList = []; return; }
        this.hostList = res.guests; this.hostLoaded = true; this.inviteCode = res.invite_code || this.inviteCode;
        this.hostSignedInLocal = true; this.hostUsernameLocal = res.username || this.hostUsernameLocal;
      } catch (err) {
        if (err.message === 'not_set_up') { this.hostLoaded = false; }
        else if (err.message === 'host_not_signed_in') { this.hostSignedInLocal = false; this.hostUsernameLocal = ''; this.hostLoaded = false; this.hostList = []; }
        else this.showToast('Could not load host data: ' + err.message, false);
      }
    },
    async refreshClaimed() {
      await this.loadHost();
      this.hostMsg = this.hostList.filter((g) => g.claimed).length + ' of ' + this.hostList.length + ' locked';
      this.showToast('Claimed status refreshed.');
    },
    async resetOne(id) {
      this.openConfirmDialog({
        title: 'Reset one name?',
        message: 'Resetting unlocks that name and removes its recipient. The name can be entered again after this.',
        okText: 'Reset name',
        onOk: async () => {
          const res = await this.api('reset_one', { guest_id: id });
          this.hostList = res.guests; this.hostMsg = this.hostList.filter((g) => g.claimed).length + ' of ' + this.hostList.length + ' locked';
          this.showToast('Name reset.');
        },
      });
    },
    async resetAll() {
      if (!this.hostSignedInLocal) { this.startHost(); return; }
      this.openConfirmDialog({
        title: 'Reset everything?',
        message: 'This clears the whole party — guests, claims, wishlists and polls. This cannot be undone.',
        okText: 'Reset everything',
        onOk: async () => {
          await this.api('reset_all', {});
          this.hostLoaded = false; this.hostList = []; this.hostSignedInLocal = false; this.hostUsernameLocal = ''; this.inviteCode = '';
          this.setupDoneLocal = false; this.showToast('Everything reset.');
        },
      });
    },
    async hostUnclaimAll() {
      this.openConfirmDialog({
        title: 'Unclaim all names?',
        message: 'This unlocks every name. Locked guests will need to enter again (and will get a new recipient only if the draw is redone — otherwise their original recipient is still assigned).',
        okText: 'Unclaim all',
        onOk: async () => { const res = await this.api('host_unclaim_all', {}); this.hostList = res.guests; this.showToast('All claims cleared.'); },
      });
    },
    async hostRedraw() {
      this.openConfirmDialog({
        title: 'Redraw the Secret Santa?',
        message: 'This re-draws who buys for whom. It is blocked while any name is claimed — reset all claims first.',
        okText: 'Redraw',
        onOk: async () => { await this.api('host_redraw', {}); this.showToast('Draw redone.'); },
      });
    },
    async hostClearWishlists() {
      this.openConfirmDialog({
        title: 'Clear all wishlists?',
        message: 'Every guest wishlist item will be deleted. This cannot be undone.',
        okText: 'Clear all wishlists',
        onOk: async () => { await this.api('host_clear_wishlists', {}); this.showToast('All wishlists cleared.'); },
      });
    },
    copyInvite() { navigator.clipboard.writeText(this.inviteCode).then(() => this.showToast('Invite code copied.')).catch(() => this.showToast('Invite code: ' + this.inviteCode)); },
    async regenInvite() {
      this.openConfirmDialog({
        title: 'Generate a new invite code?',
        message: 'The old invite code and QR will stop working. Anyone still using the old link will need the new one.',
        okText: 'Generate new code',
        onOk: async () => { const res = await this.api('invite_regen', {}); this.inviteCode = res.invite_code; this.showToast('New invite code generated.'); },
      });
    },
    async doSetup() {
      this.savingSetup = true;
      try {
        const guests = this.guestRows.map((r) => ({ display: r.display, nickname: r.nickname, aliases: r.aliasesText ? r.aliasesText.split(',').map((x) => x.trim()).filter(Boolean) : [], pin: r.pin || '' }));
        if (!this.setupDoneLocal && guests.some((g) => !/^\d{4}$/.test(g.pin || ''))) { this.showToast('Every guest needs a 4-digit code — check the guest list.', false); return; }
        const existing = this.setupDoneLocal;
        let res;
        if (existing) {
          res = await this.api('party_update', { party: this.partyName, budget: String(this.partyBudget), date: this.partyDateLocal });
          this.showToast('Party updated.');
        } else {
          res = await this.api('setup', { party: this.partyName, budget: String(this.partyBudget), date: this.partyDateLocal, guests });
          this.setupDoneLocal = true; this.hostSignedInLocal = true; this.showToast('Party saved.');
        }
        if (res) { this.localParty = res.party; this.localBudget = res.budget; this.partyDateLocal = res.date || this.partyDateLocal; }
        // Create only: the draft guest list is now saved. Edit only
        // changes party details — it must never clear/lose guests.
        // Create only: the in-memory draft list is now saved and discarded.
        if (!existing) { this.guestRows = []; }
        this.openDashboard();
      } catch (e) {
        if (e.message === 'host_not_signed_in') { this.showToast('Sign in as host first.', false); this.startHost(); }
        else if (e.message === 'already_set_up') this.showToast('A party is already set up. To start over, reset everything on the dashboard.', false);
        else if (e.message === 'pin_must_be_4_digits') this.showToast('Every guest needs a 4-digit code — check the guest list.', false);
        else this.showToast('Could not save: ' + e.message, false);
      } finally { this.savingSetup = false; }
    },
    openChangePasswordModal() { this.changePassCurrent = ''; this.changePassNew = ''; this.changePassConfirm = ''; this.changePassMsg = ''; this.showChangePasswordModal = true; },
    closeChangePasswordModal() { this.showChangePasswordModal = false; },
    async doChangePassword() {
      this.changePassMsg = '';
      if (this.changePassNew.length < 4) { this.changePassMsg = 'New password must be at least 4 characters.'; return; }
      if (this.changePassNew !== this.changePassConfirm) { this.changePassMsg = 'New password and confirm password do not match.'; return; }
      this.changingPassword = true;
      try {
        await this.api('host_change_password', { current_password: this.changePassCurrent, new_password: this.changePassNew });
        this.showChangePasswordModal = false; this.showToast('Password changed.');
      } catch (err) { this.changePassMsg = 'Could not change password: ' + err.message; }
      finally { this.changingPassword = false; }
    },
    openGuestModal(i, context = 'saved') {
      this.guestModalContext = context;
      this.guestEditIndex = i;
      if (context === 'saved') {
        // Real saved-party guests (hostList from host_status), not draft.
        if (i >= 0) { const g = this.hostList[i]; this.guestForm = { id: g.id, display: g.display, nickname: g.nickname || '', aliasesText: (g.aliases || []).join(', '), pin: '' }; }
        else this.guestForm = { id: '', display: '', nickname: '', aliasesText: '', pin: '' };
      } else {
        if (i >= 0) this.guestForm = { ...this.guestRows[i] };
        else this.guestForm = { display: '', nickname: '', aliasesText: '', pin: '' };
      }
      this.showGuestModal = true;
    },
    closeGuestModal() { this.showGuestModal = false; },
    async saveGuestModal() {
      if (!this.guestForm.display.trim()) { this.showToast('Guest name is required.', false); return; }
      // PIN is required only for NEW guests. Editing a saved guest
      // must never force a code: blank keeps the existing code, or
      // none for guests created before codes were mandatory.
      const pinRequired = this.guestEditIndex < 0;
      if (pinRequired && !/^\d{4}$/.test(this.guestForm.pin || '')) { this.showToast('Guest PIN is required — enter exactly 4 digits.', false); return; }
      if (!pinRequired && this.guestForm.pin && !/^\d{4}$/.test(this.guestForm.pin)) { this.showToast('Guest PIN must be exactly 4 digits (or leave it blank to keep the existing code).', false); return; }
      if (this.guestModalContext === 'saved') {
        // Real guest management: backend re-draws after every change.
        // Blocked server-side (409) if any name is already locked.
        const body = { display: this.guestForm.display, nickname: this.guestForm.nickname, aliases: this.guestForm.aliasesText ? this.guestForm.aliasesText.split(',').map((x) => x.trim()).filter(Boolean) : [], pin: this.guestForm.pin || '' };
        try {
          const res = this.guestEditIndex >= 0
            ? await this.api('host_guest_update', { ...body, id: this.guestForm.id })
            : await this.api('host_guest_add', body);
          this.hostList = res.guests; this.showGuestModal = false;
          this.showToast(this.guestEditIndex >= 0 ? 'Guest updated — draw re-done.' : 'Guest added — draw re-done.');
        } catch (e) { this.showToast(e.message === 'Cannot change guests after names have been locked — reset claims first' ? 'Guest list is locked — reset claims first.' : e.message === 'pin_must_be_4_digits' ? 'Guest PIN is required — enter exactly 4 digits.' : 'Could not save guest: ' + e.message, false); }
        return;
      }
      // Create-party draft only (setup page, no party yet).
      if (this.guestEditIndex >= 0) this.guestRows.splice(this.guestEditIndex, 1, { ...this.guestForm });
      else this.guestRows.push({ ...this.guestForm });
      // Create-party draft only, in-memory — no localStorage persistence.
      this.showGuestModal = false; this.showToast(this.guestEditIndex >= 0 ? 'Guest updated.' : 'Guest added.');
    },
    removeSavedGuest(g) {
      this.openConfirmDialog({
        title: 'Remove this guest?',
        message: 'Removing ' + g.display + ' also removes their wishlist and re-draws the Secret Santa. This cannot be undone.',
        okText: 'Remove guest',
        onOk: async () => {
          try { const res = await this.api('host_guest_remove', { id: g.id }); this.hostList = res.guests; this.showToast('Guest removed — draw re-done.'); }
          catch (e) { this.showToast(e.message === 'Cannot change guests after names have been locked — reset claims first' ? 'Guest list is locked — reset claims first.' : 'Could not remove guest: ' + e.message, false); }
        },
      });
    },
    removeGuestRow(i) { this.guestRows.splice(i, 1); },
    togglePollMenu(id) { this.pollMenuId = this.pollMenuId === id ? null : id; },
    openPollModal() { this.pollQuestion = 'What should our Christmas theme be?'; this.pollOptionsText = 'Ugly Sweater\nWinter Wonderland\nPajama Party\nRed & Gold'; this.showPollModal = true; },
    closePollModal() { this.showPollModal = false; },
    async setupPoll() {
      const options = this.pollOptionsText.split('\n').map((s) => s.trim()).filter(Boolean);
      if (!this.pollQuestion.trim()) { this.showToast('Question is required.', false); return; }
      if (options.length < 2) { this.showToast('At least 2 options are required.', false); return; }
      try {
        await this.api('poll_setup', { question: this.pollQuestion, options });
        this.showPollModal = false; this.pollQuestion = ''; this.pollOptionsText = ''; await this.loadPoll(); this.showToast('Poll created.');
      } catch (e) { this.showToast('Could not create poll: ' + e.message, false); }
    },
    async setPollOpen(pollId, open) {
      try { const d = await this.api('poll_set_open', { poll_id: pollId, open }); this.localPolls = d.polls; this.pollMenuId = null; this.showToast(open ? 'Poll reopened.' : 'Poll closed.'); }
      catch (e) { this.showToast('Could not update poll: ' + e.message, false); }
    },
    resetPoll(p) {
      this.openConfirmDialog({
        title: 'Delete this poll?', message: 'This poll and all its votes will be deleted. This cannot be undone.', okText: 'Delete poll',
        onOk: async () => { await this.api('poll_reset', { poll_id: p.id }); this.pollMenuId = null; await this.loadPoll(); this.showToast('Poll deleted.'); },
      });
    },
    openEditPollModal(p) {
      this.editPollId = p.id; this.editPollQuestion = p.question; this.editPollOptionsText = p.options.map((o) => o.text).join('\n'); this.showEditPollModal = true;
    },
    closeEditPollModal() { this.showEditPollModal = false; },
    async doEditPoll() {
      const options = this.editPollOptionsText.split('\n').map((s) => s.trim()).filter(Boolean);
      if (!this.editPollQuestion.trim()) { this.showToast('Question is required.', false); return; }
      if (options.length < 2) { this.showToast('At least 2 options are required.', false); return; }
      try { const d = await this.api('poll_edit', { poll_id: this.editPollId, question: this.editPollQuestion, options }); this.localPolls = d.polls; this.showEditPollModal = false; this.pollMenuId = null; this.showToast('Poll updated.'); }
      catch (e) { this.showToast('Could not update poll: ' + e.message, false); }
    },
    /* ---------- generic confirm ---------- */
    openConfirmDialog(opts) { this.confirmDialogTitle = opts.title; this.confirmDialogMessage = opts.message; this.confirmDialogOkText = opts.okText || 'OK'; this.confirmDialogCallback = opts.onOk; this.showConfirmDialog = true; },
    closeConfirmDialog() { this.showConfirmDialog = false; },
    async confirmDialogOk() {
      try { if (this.confirmDialogCallback) await this.confirmDialogCallback(); this.showConfirmDialog = false; }
      catch (e) { this.showToast('Could not complete: ' + e.message, false); }
    },
  },
};
</script>

<style>
body { background: #000; color: #fafafa; font-family: Inter, system-ui, sans-serif; }
a { color: #34d399; }
</style>
