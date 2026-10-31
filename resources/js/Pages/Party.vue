<template>
  <div id="app">
    <div class="grain" aria-hidden="true"></div>
    <div class="snow-bg" aria-hidden="true">
      <span v-for="(s, i) in snow" :key="i" :style="{ left: s.left, top: s.top, fontSize: s.size, opacity: s.opacity }">❄</span>
    </div>

    <nav v-if="isHostView" class="host-nav" aria-label="Host navigation">
      <div class="host-nav-inner">
        <button class="host-nav-btn" :class="{ active: view === 'hostdashboard' }" @click="openDashboard"><LayoutDashboard class="w-5 h-5" /><span>Dashboard</span></button>
        <button class="host-nav-btn" :class="{ active: view === 'hostguests' }" @click="goTo('hostguests', '/host/guests')"><Users class="w-5 h-5" /><span>Guests</span></button>
        <button class="host-nav-btn" :class="{ active: view === 'hostpoll' }" @click="goTo('hostpoll', '/host/poll')"><Vote class="w-5 h-5" /><span>Poll</span></button>
        <button class="host-nav-btn" :class="{ active: view === 'hostsetup' }" @click="goTo('hostsetup', '/host/setup')"><Settings class="w-5 h-5" /><span>Setup</span></button>
        <span class="flex-1"></span>
        <button class="btn btn-success btn-sm" style="width:auto" @click="openGuestModal(-1)"><UserRoundPlus class="w-4 h-4" /> Add guest</button>
        <span v-if="hostSignedInLocal" class="text-sm opacity-80">Host signed in</span>
        <button v-if="hostSignedInLocal" class="btn btn-sm btn-ghost" style="width:auto" @click="hostSignOut">Sign out</button>
      </div>
    </nav>

    <main class="w-full mx-auto px-3 py-3" :class="isHostMain ? 'host-main' : 'max-w-2xl'" style="position:relative;z-index:1;padding-bottom:96px">
      <!-- HOME (guest-only, no host link) -->
      <section v-show="view === 'home'">
        <div class="flex items-start gap-2">
          <div class="shrink-0 text-center" style="width:90px;height:90px;border-radius:9999px;background:#f8bbd0;display:flex;align-items:center;justify-content:center"><Gift class="w-12 h-12" style="color:#774f38" /></div>
          <div class="santa-bubble flex-1">
            <span v-if="!setupDoneLocal">Ho ho ho! The party isn't set up yet — check back once your host has set it up.</span>
            <span v-else>Ho ho ho! Scan the QR — you'll land straight on the guest page. Search your name, and let's get gifting.</span>
          </div>
        </div>
        <div class="segmented mt-4">
          <button class="seg-btn" :disabled="!setupDoneLocal" @click="startGuest"><Gift class="w-6 h-6" /> I'm a guest</button>
        </div>
        <button class="btn btn-outline w-full mt-3" @click="openQrModal"><QrCode class="w-5 h-5" /> Scan QR code</button>
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
          <h2 class="text-2xl font-black">Who are you?</h2>
          <p class="text-sm opacity-70 mt-1">Search your name, then confirm it's you.</p>
          <div class="flex gap-2 mt-3">
            <input class="input" v-model="search" placeholder="Type your name…" @keyup.enter="guestSearched = true" />
            <button class="btn btn-primary shrink-0" style="width:auto" @click="guestSearched = true"><Search class="w-5 h-5" /> Search</button>
          </div>
          <div v-if="guestSearched" class="mt-4">
            <div v-if="!filteredGuests.length" class="text-sm opacity-70">No match — try a nickname or just part of your name.</div>
            <button v-for="g in filteredGuests" :key="g.id" class="w-full text-left p-3 mt-2 card" style="border-radius:1rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:inherit;cursor:pointer" @click="selectGuest(g)">
              <b>{{ g.display }}</b><span v-if="g.nickname"> “{{ g.nickname }}”</span><span v-if="g.claimed" class="text-error font-bold"> — Locked</span>
            </button>
          </div>
        </div>
      </section>

      <!-- REVEAL -->
      <section v-show="view === 'reveal'">
        <div class="card m3-card p-4">
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
      </section>

      <!-- GIFTS (my wishlist) -->
      <section v-show="view === 'gifts'">
        <div class="card m3-card p-4">
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
          <h2 class="text-2xl font-black">Everyone's wishlists</h2>
          <input class="input mt-3" v-model="wishSearch" placeholder="Search a name…" />
          <div v-if="!filteredWishBoard.length" class="text-sm opacity-70 mt-3">{{ localWishBoard.length ? 'No match.' : 'No members yet.' }}</div>
          <button v-for="o in filteredWishBoard" :key="o.id" class="w-full text-left p-3 mt-2 card" style="border-radius:1rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:inherit;cursor:pointer" @click="openWishOwnerModal(o)">
            <b>{{ o.display }}</b><span v-if="o.nickname"> “{{ o.nickname }}”</span><span v-if="guestMe && o.id === guestMe.id"> — you</span>
            <span class="text-sm opacity-70"> • {{ o.items.length ? o.items.length + ' wish(es)' : 'no wishlist yet' }}</span>
            <ChevronRight class="w-4 h-4 inline float-right" />
          </button>
        </div>
      </section>

      <!-- VOTE -->
      <section v-show="view === 'vote'">
        <div class="card m3-card p-4">
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

      <!-- HOST DASHBOARD -->
      <section v-show="view === 'hostdashboard'">
        <div class="card m3-card p-4">
          <h2 class="text-2xl font-black">Host dashboard</h2>
          <p class="text-sm opacity-70 mt-1">Who has locked in, and how guests join. Recipients are never shown here.</p>
          <div class="grid grid-cols-3 gap-2 mt-4">
            <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"><div class="text-2xl font-black">{{ hostLoaded ? hostList.length : '—' }}</div><div class="text-xs opacity-70">Guests</div></div>
            <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"><div class="text-2xl font-black">{{ hostLoaded ? hostList.filter(g => g.claimed).length : '—' }}</div><div class="text-xs opacity-70">Locked</div></div>
            <div class="rounded-xl p-3 text-center" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)"><div class="text-2xl font-black">{{ hostLoaded ? hostList.filter(g => !g.claimed).length : '—' }}</div><div class="text-xs opacity-70">Awaiting</div></div>
          </div>
          <div v-if="!hostSignedInLocal && !hostLoaded" class="mt-4">
            <p class="text-sm opacity-70">Enter your host PIN to load your party stats.</p>
            <p class="text-sm opacity-70 mt-1">First time? The default PIN is 1234 — you'll set a new one after signing in.</p>
            <button class="btn btn-sm mt-2" style="width:auto" @click="startHost">Sign in</button>
            <button v-if="!setupDoneLocal" class="btn btn-sm mt-2" style="width:auto" @click="goTo('hostsetup', '/host/setup')">Go to Setup</button>
          </div>
          <div v-if="!setupDoneLocal" class="mt-4">
            <p class="text-sm opacity-70">No party set up yet — set one up first, then this dashboard will show guests, claimed status and the invite code.</p>
            <button class="btn btn-sm mt-2" style="width:auto" @click="goTo('hostsetup', '/host/setup')">Go to Setup</button>
          </div>
          <div class="host-dash-grid">
            <div v-if="hostLoaded">
              <div class="flex items-center justify-between gap-3"><div><h3 class="font-black">Claimed status</h3><p class="text-sm opacity-70 mt-1">See who has locked in, and reset a name.</p></div><button class="btn btn-sm shrink-0" style="width:auto" @click="openClaimedModal">View</button></div>
            </div>
            <div v-if="hostLoaded">
              <h3 class="font-black"><Ticket class="w-4 h-4 inline" /> Invite code</h3>
              <p class="text-sm opacity-70 mt-1">Guests enter this code to join. No login needed.</p>
              <p class="text-xl font-black tracking-widest mt-2">{{ inviteCode || '—' }}</p>
              <div class="flex gap-2 mt-2"><button class="btn btn-sm shrink-0" style="width:auto" @click="copyInvite">Copy code</button><button class="btn btn-sm btn-ghost shrink-0" style="width:auto" @click="regenInvite">New code</button></div>
            </div>
            <div v-if="qrUrl && hostLoaded">
              <h3 class="font-black"><QrCode class="w-4 h-4 inline" /> Single QR</h3>
              <p class="text-sm opacity-70 mt-1">Print this — everyone scans the same one. The invite code is built in.</p>
              <img :src="qrUrl" alt="Secret Santa QR code" class="mx-auto my-3" style="max-width:180px;width:100%" />
              <div class="text-center"><button class="btn btn-sm shrink-0" style="width:auto" onclick="window.print()"><Printer class="w-4 h-4 inline" /> Print QR sheet</button></div>
            </div>
            <div v-if="hostLoaded">
              <h3 class="font-black text-error">Danger zone</h3>
              <p class="text-sm opacity-70 mt-1">Deletes the whole draw and all claims. This cannot be undone.</p>
              <button class="btn btn-sm btn-error mt-2" style="width:auto" @click="resetAll">Reset everything</button>
            </div>
          </div>
        </div>
      </section>

      <!-- HOST SETUP -->
      <section v-if="view === 'hostsetup'">
        <div class="card m3-card p-4">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-2xl font-black">Setup</h2>
            <button class="btn btn-success btn-sm" style="width:auto" :disabled="savingSetup" @click="doSetup"><span v-if="savingSetup">Saving…</span><span v-else><Check class="w-5 h-5 inline" /> Save</span></button>
          </div>
          <label class="block mt-3">Party name <input class="input" v-model="partyName" /></label>
          <p class="block mt-2">Gift budget</p>
          <div class="flex flex-wrap gap-2 mt-2 items-center">
            <button class="btn btn-sm" style="width:auto" :class="{ 'btn-primary': partyBudget === '250' }" @click="partyBudget = '250'">₱250</button>
            <button class="btn btn-sm" style="width:auto" :class="{ 'btn-primary': partyBudget === '500' }" @click="partyBudget = '500'">₱500</button>
            <button class="btn btn-sm" style="width:auto" :class="{ 'btn-primary': partyBudget === '1000' }" @click="partyBudget = '1000'">₱1000</button>
            <div class="relative flex-1" style="min-width:120px"><span class="absolute left-3 top-1/2 opacity-70" style="transform:translateY(-50%)">₱</span><input class="input" style="padding-left:28px" v-model="partyBudget" inputmode="numeric" placeholder="Custom amount" /></div>
          </div>
          <div class="host-setup-grid">
            <label class="block">Party date <input class="input" type="date" v-model="partyDateLocal" /></label>
            <label class="block">Host PIN (4 digits, or leave blank for the default 1234) <InputOtp v-model="hostPin" type="password" /></label>
          </div>
          <p class="text-sm opacity-70 mt-1">Leave it blank and the default PIN 1234 is used — you'll be asked to set a new 4-digit PIN after signing in.</p>
          <div v-if="setupDoneLocal" class="mt-2"><button class="btn btn-sm btn-ghost" style="width:auto" @click="openResetPinModal">Reset PIN</button></div>
        </div>
      </section>

      <!-- HOST POLLS -->
      <section v-show="view === 'hostpoll'">
        <div class="card m3-card p-4">
          <h2 class="text-2xl font-black">Polls</h2>
          <p class="text-sm opacity-70 mt-1">All polls, newest first. Guests can vote on open polls; closed polls show their final winner.</p>
          <div v-if="!setupDoneLocal" class="mt-4"><p class="text-sm opacity-70">No party set up yet — set one up first, then you can create polls here.</p><button class="btn btn-sm mt-2" style="width:auto" @click="goTo('hostsetup', '/host/setup')">Go to Setup</button></div>
          <div v-if="!localPolls.length" class="mt-4 opacity-70">No polls yet — tap Create poll to add your first one.</div>
          <div v-else class="host-polls-grid">
            <div v-for="p in localPolls.slice().reverse()" :key="p.id" class="rounded-xl p-3" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1)">
              <div class="flex items-start gap-3"><div class="flex-1 min-w-0"><b>{{ p.question }}</b><p class="text-sm opacity-70 mt-1">{{ p.open ? 'Voting open' : 'Poll closed' }} • {{ p.total }} vote(s)</p><p v-if="!p.open && p.options.length" class="text-sm mt-1">Winner: <b>{{ p.options.reduce((a, b) => b.votes > a.votes ? b : a).text }}</b></p></div><button class="btn btn-ghost shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Poll settings" @click="togglePollMenu(p.id)"><Settings2 class="w-5 h-5" /></button></div>
              <div v-if="pollMenuId === p.id" class="flex flex-wrap gap-2 mt-3">
                <button class="btn btn-sm shrink-0" style="width:auto" @click="openEditPollModal(p)"><Pencil class="w-4 h-4" /> Edit</button>
                <button class="btn btn-sm shrink-0" style="width:auto" @click="setPollOpen(p.id, !p.open)">{{ p.open ? 'Close' : 'Reopen' }}</button>
                <button class="btn btn-sm btn-error shrink-0" style="width:auto" @click="resetPoll(p)">Delete</button>
              </div>
            </div>
          </div>
          <div class="flex justify-end mt-4"><button class="btn btn-sm shrink-0" style="width:auto" :disabled="!setupDoneLocal" @click="openPollModal"><Plus class="w-4 h-4" /> Create poll</button></div>
        </div>
      </section>

      <!-- HOST GUESTS -->
      <section v-show="view === 'hostguests'">
        <div class="card m3-card p-4">
          <h2 class="text-2xl font-black">Guests ({{ guestRows.length }})</h2>
          <p v-if="!guestRows.length" class="text-sm opacity-70 mt-1">No guests yet. Tap Add guest to add your first one.</p>
          <div class="host-guests-grid">
            <div v-for="(r, i) in guestRows" :key="i" class="card p-3" style="border-radius:1rem">
              <div class="flex items-start gap-2"><div class="flex-1"><b>{{ r.display }}</b><span v-if="r.nickname"> “{{ r.nickname }}”</span><span v-if="r.aliasesText" class="text-sm opacity-60"> — also: {{ r.aliasesText }}</span><span v-if="r.pin" class="text-sm opacity-60"> • PIN set</span></div>
              <div class="flex gap-1 shrink-0">
                <button class="btn btn-ghost shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Remove guest" @click="removeGuestRow(i)"><Trash2 class="w-4 h-4" /></button>
                <button class="btn btn-ghost shrink-0" style="width:36px;height:36px;min-height:36px;padding:0" aria-label="Edit guest" @click="openGuestModal(i)"><Pencil class="w-4 h-4" /></button>
              </div></div>
            </div>
          </div>
          <div class="flex justify-end mt-4"><button class="btn btn-sm shrink-0" style="width:auto" @click="openGuestModal(-1)">+ Add guest</button></div>
        </div>
      </section>

      <!-- SUPERADMIN (no visible link in the app) -->
      <section v-show="view === 'superadmin'">
        <div class="card m3-card p-4">
          <h2 class="text-2xl font-black">Superadmin</h2>
          <div v-if="!superadminSignedIn">
            <div v-if="!superadminSet">
              <p class="text-sm opacity-70 mt-1">First visit: set a username and password. They are stored only as a hash on the server.</p>
              <label class="block mt-3">Username <input class="input" v-model="superadminUser" /></label>
              <label class="block mt-2">Password <input class="input" type="password" v-model="superadminPass" /></label>
              <button class="btn btn-success w-full mt-3" @click="superadminSetup">Set up superadmin</button>
            </div>
            <div v-else>
              <label class="block mt-3">Username <input class="input" v-model="superadminUser" /></label>
              <label class="block mt-2">Password <input class="input" type="password" v-model="superadminPass" /></label>
              <button class="btn btn-success w-full mt-3" @click="superadminLogin">Sign in</button>
            </div>
          </div>
          <div v-else>
            <div class="flex justify-between items-center"><p class="text-sm opacity-70">Signed in as {{ superadminUser }}</p><button class="btn btn-sm btn-ghost" style="width:auto" @click="superadminLogout">Sign out</button></div>
            <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,0.1)">
              <h3 class="font-black">Party config</h3>
              <label class="block mt-3">Party name <input class="input" v-model="superadminParty" /></label>
              <label class="block mt-2">Gift budget <input class="input" v-model="superadminBudget" placeholder="e.g. 500" /></label>
              <label class="block mt-2">Party date <input type="date" class="input" v-model="superadminDate" /></label>
              <button class="btn btn-success w-full mt-3" @click="saveSuperadminParty">Save party details</button>
            </div>
            <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,0.1)">
              <h3 class="font-black">Invite code</h3>
              <div class="flex items-center gap-2 mt-3"><p class="text-lg font-black flex-1" style="letter-spacing:0.2em">{{ superadminInvite }}</p><button class="btn btn-sm shrink-0" style="width:auto" @click="regenSuperadminInvite">New code</button></div>
              <label class="block mt-3">New host PIN (4 digits) <InputOtp v-model="superadminNewPin" type="password" /></label>
              <button class="btn btn-sm shrink-0 mt-2" style="width:auto" @click="setSuperadminHostPin">Set host PIN</button>
            </div>
            <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,0.1)">
              <h3 class="font-black">Guests ({{ superadminGuests.length }})</h3>
              <div v-for="g in superadminGuests" :key="g.id" class="flex items-center gap-2 py-2" style="border-bottom:1px solid rgba(255,255,255,0.1)"><span class="flex-1"><b>{{ g.display }}</b><span v-if="g.nickname"> “{{ g.nickname }}”</span><span class="text-sm opacity-70">{{ g.claimed ? ' • Locked' : ' • Not yet' }}</span></span><button v-if="g.claimed" class="btn btn-sm shrink-0" style="width:auto" @click="superadminResetOne(g)">Reset claim</button></div>
              <div class="flex flex-wrap gap-2 mt-3"><button class="btn btn-sm shrink-0" style="width:auto" @click="superadminUnclaimAll">Unclaim all</button><button class="btn btn-sm shrink-0" style="width:auto" @click="superadminRedraw">Redraw</button><button class="btn btn-sm shrink-0" style="width:auto" @click="superadminClearWishlists">Clear all wishlists</button></div>
            </div>
            <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,0.1)">
              <h3 class="font-black">Polls</h3>
              <div v-for="pl in superadminPolls" :key="pl.id" class="flex items-center gap-2 py-2" style="border-bottom:1px solid rgba(255,255,255,0.1)"><span class="flex-1"><b>{{ pl.question }}</b><span class="text-sm opacity-70">{{ pl.open ? ' • Open' : ' • Closed' }} • {{ pl.total }} vote(s)</span></span><button class="btn btn-sm shrink-0" style="width:auto" @click="superadminPollOpen(pl, !pl.open)">{{ pl.open ? 'Close' : 'Reopen' }}</button><button class="btn btn-sm btn-error shrink-0" style="width:auto" @click="superadminPollDelete(pl)">Delete</button></div>
            </div>
            <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,0.1)">
              <h3 class="font-black">Change password</h3>
              <label class="block mt-2">New password <input class="input" type="password" v-model="superadminNewPass" /></label>
              <button class="btn btn-sm mt-2" style="width:auto" @click="changeSuperadminPassword">Change password</button>
            </div>
            <div class="mt-4 pt-4" style="border-top:1px solid rgba(255,255,255,0.1)">
              <h3 class="font-black text-error">Danger zone</h3>
              <button class="btn btn-sm btn-error mt-2" style="width:auto" @click="superadminResetAll">Reset everything</button>
            </div>
          </div>
        </div>
      </section>
    </main>

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
        <div v-if="selected && selected.needs_pin" class="mt-3"><InputOtp v-model="guestPin" type="password" /></div>
        <div class="flex gap-2 mt-auto pt-6">
          <button class="btn btn-ghost flex-1" @click="closeConfirmModal">No, go back</button>
          <button v-if="selected && !selected.claimed" class="btn btn-success flex-1" @click="confirmEnter">Yes, that's me</button>
          <button v-if="selected && selected.claimed" class="btn btn-success flex-1" @click="confirmClaimedGuest">Yes, that's me</button>
        </div>
      </div>
    </div>

    <!-- Claimed status -->
    <div v-if="showClaimedModal" class="modal-backdrop" @click.self="closeClaimedModal">
      <div class="modal-card">
        <div class="flex items-center justify-between gap-3"><h3 class="text-xl font-black">Claimed status</h3><button class="btn btn-ghost btn-sm" style="width:auto" @click="closeClaimedModal">×</button></div>
        <p class="text-sm opacity-70 mt-1">Recipients are never shown here — only whether each name is locked.</p>
        <div class="flex items-center justify-between gap-3 mt-3"><p v-if="hostMsg" class="text-sm flex-1" :class="hostOk ? 'opacity-70' : 'text-error'">{{ hostMsg }}</p><button class="btn btn-sm ml-auto" style="width:auto" @click="refreshClaimed">Refresh</button></div>
        <div v-for="g in hostList" :key="g.id" class="flex items-center gap-3 mt-3"><span class="flex-1"><b>{{ g.display }}</b><span v-if="g.nickname"> “{{ g.nickname }}”</span></span><span v-if="g.claimed" class="text-error text-sm font-bold shrink-0">Locked</span><span v-else class="text-sm opacity-60 shrink-0">Not yet locked</span><button v-if="g.claimed" class="btn btn-sm btn-ghost shrink-0" style="width:auto" @click="resetOne(g.id)">Reset</button></div>
      </div>
    </div>

    <!-- Host entry -->
    <div v-if="showHostEntryModal" class="modal-backdrop" @click.self="closeHostEntryModal">
      <div class="modal-card">
        <div class="flex items-center justify-between"><h3 class="text-xl font-black">Host sign-in</h3><button class="btn btn-ghost btn-sm" style="width:auto" @click="closeHostEntryModal">×</button></div>
        <p class="text-sm opacity-70 mt-1">Sign in to manage your party.</p>
        <button class="btn btn-primary w-full mt-4" :disabled="!setupDoneLocal" @click="enterHostWithPin"><KeyRound class="w-5 h-5" /> Enter PIN</button>
        <button class="btn btn-ghost w-full mt-2" @click="closeHostEntryModal">Cancel</button>
      </div>
    </div>

    <!-- Host PIN popup -->
    <div v-if="showHostPinModal" class="modal-backdrop" @click.self="closeHostPinModal">
      <div class="modal-card">
        <div :class="{ 'otp-error': hostPinEntryError }"><InputOtp v-model="hostPinEntry" type="password" @enter="submitHostPin" /></div>
        <p v-if="hostPinEntryError" class="text-sm text-error mt-2">Incorrect PIN — try again.</p>
        <p class="text-sm opacity-70 mt-2">First time? The default PIN is 1234.</p>
        <button class="btn btn-primary w-full mt-3" @click="submitHostPin">Continue</button>
      </div>
    </div>

    <!-- Forced new host PIN -->
    <div v-if="showForcePinModal" class="modal-backdrop">
      <div class="modal-card">
        <h3 class="text-xl font-black">Set a new host PIN</h3>
        <p class="text-sm opacity-70 mt-1">You're signed in with the default PIN 1234. Set a new 4-digit PIN to continue — 1234 will stop working.</p>
        <div class="mt-3"><InputOtp v-model="forcePinNew" type="password" /></div>
        <div class="mt-2"><InputOtp v-model="forcePinConfirm" type="password" /></div>
        <p v-if="forcePinMsg" class="text-sm text-error mt-2">{{ forcePinMsg }}</p>
        <button class="btn btn-success w-full mt-3" :disabled="resettingForcePin" @click="doForcePin">{{ resettingForcePin ? 'Setting…' : 'Set new PIN' }}</button>
      </div>
    </div>

    <!-- Reset PIN -->
    <div v-if="showResetPinModal" class="modal-backdrop" @click.self="closeResetPinModal">
      <div class="modal-card">
        <div class="flex items-center justify-between"><h3 class="text-xl font-black">Reset host PIN</h3><button class="btn btn-ghost btn-sm" style="width:auto" @click="closeResetPinModal">×</button></div>
        <div class="mt-3"><div v-if="!hostSignedInLocal"><InputOtp v-model="resetPinCurrent" type="password" /></div><div class="mt-2"><InputOtp v-model="resetPinNew" type="password" /></div><div class="mt-2"><InputOtp v-model="resetPinConfirm" type="password" /></div><button class="btn btn-success w-full mt-3" :disabled="resettingPin" @click="doResetPin">{{ resettingPin ? 'Resetting…' : 'Reset PIN' }}</button></div>
        <button class="btn btn-ghost w-full mt-2" @click="closeResetPinModal">Cancel</button>
      </div>
    </div>

    <!-- Add/Edit guest -->
    <div v-if="showGuestModal" class="modal-backdrop" @click.self="closeGuestModal">
      <div class="modal-card">
        <h3 class="text-xl font-black">{{ guestEditIndex >= 0 ? 'Edit guest' : 'Add guest' }}</h3>
        <label class="block mt-3">Name <input class="input" v-model="guestForm.display" /></label>
        <label class="block mt-2">Nickname <input class="input" v-model="guestForm.nickname" /></label>
        <label class="block mt-2">Aliases (comma-separated) <input class="input" v-model="guestForm.aliasesText" /></label>
        <label class="block mt-2">Guest PIN (optional, 4 digits) <InputOtp v-model="guestForm.pin" type="password" /></label>
        <div class="flex gap-2 mt-4"><button class="btn btn-ghost flex-1" @click="closeGuestModal">Cancel</button><button class="btn btn-success flex-1" @click="saveGuestModal">Save</button></div>
      </div>
    </div>

    <!-- Wishlist form -->
    <div v-if="showWishModal" class="modal-backdrop" @click.self="closeWishModal">
      <div class="modal-card">
        <h3 class="text-xl font-black">Add to my wishlist</h3>
        <label class="block mt-3">Wish <input class="input" v-model="wishText" /></label>
        <label class="block mt-2">Link <input class="input" v-model="wishLink" placeholder="https://…" /></label>
        <label class="block mt-2">Screenshot <input type="file" accept="image/*" @change="onWishImageFile" /></label>
        <img v-if="wishImage" :src="wishImage" alt="" style="max-width:100%;border-radius:0.75rem;margin-top:8px" />
        <label class="block mt-2">Notes <textarea class="input" v-model="wishNotes" placeholder="Where to buy it — shop, directions, size / colour (optional)"></textarea></label>
        <div class="flex gap-2 mt-4"><button class="btn btn-ghost flex-1" @click="closeWishModal">Cancel</button><button class="btn btn-success flex-1" @click="addWishItem">Add</button></div>
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
import {
  ArrowRight, Check, ChevronRight, ExternalLink, Gift, Home, KeyRound,
  LayoutDashboard, LogOut, Pencil, Plus, Printer, QrCode, Search, Settings,
  Settings2, ShoppingBag, StarPlus, Ticket, Trash2, UserRoundPlus, Users,
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
    InputOtp, ArrowRight, Check, ChevronRight, ExternalLink, Gift, Home, KeyRound,
    LayoutDashboard, LogOut, Pencil, Plus, Printer, QrCode, Search, Settings,
    Settings2, ShoppingBag, StarPlus, Ticket, Trash2, UserRoundPlus, Users, Vote, X,
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
    defaultPinActive: { type: Boolean, default: false },
  },
  data() {
    return {
      localGuests: this.guests, localWishBoard: this.wishBoard, localPolls: this.polls,
      localParty: this.party, localBudget: this.budget, setupDoneLocal: this.setupDone,
      view: this.initialView,
      fabOpen: false,
      search: '', guestSearched: false, selected: null, showConfirmModal: false,
      showClaimedModal: false, showConfirmDialog: false, confirmDialogTitle: '', confirmDialogMessage: '',
      confirmDialogOkText: 'OK', confirmDialogCallback: null,
      guestPin: '', guestMe: null, guestRecipient: null, guestAlreadyClaimed: false,
      wishModalOwner: null, showWishOwnerModal: false, pendingWishOwnerId: null,
      partyName: this.party, partyBudget: this.budget, partyDateLocal: this.partyDate,
      hostPin: '', showResetPinModal: false, resetPinCurrent: '', resetPinNew: '', resetPinConfirm: '',
      resettingPin: false, guestRows: [], showGuestModal: false, guestEditIndex: -1,
      guestForm: { display: '', nickname: '', aliasesText: '', pin: '' },
      savingSetup: false, toastMsg: '', toastOk: true, toastTimer: null,
      hostMsg: '', hostOk: false, hostList: [], hostLoaded: false, inviteCode: '',
      showHostEntryModal: false, showHostPinModal: false, hostPinEntry: '', hostPinEntryError: false,
      hostSignedInLocal: this.hostSignedIn, defaultPinActiveLocal: this.defaultPinActive,
      showForcePinModal: false, forcePinNew: '', forcePinConfirm: '', forcePinMsg: '', resettingForcePin: false,
      wishMe: null, wishSearch: '', wishText: '', wishLink: '', wishNotes: '', wishPin: '',
      wishImage: '', wishImageName: '', showWishModal: false,
      pollQuestion: 'What should our Christmas theme be?', pollOptionsText: 'Ugly Sweater\nWinter Wonderland\nPajama Party\nRed & Gold',
      showPollModal: false, pollMenuId: null, showEditPollModal: false, editPollId: '', editPollQuestion: '', editPollOptionsText: '',
      showGuestEntryModal: false, guestEntryCode: '', showQrModal: false,
      superadminSet: false, superadminSignedIn: false, superadminSession: '', superadminUser: '', superadminPass: '',
      superadminParty: '', superadminBudget: '', superadminDate: '', superadminInvite: '', superadminGuests: [],
      superadminPolls: [], superadminNewPin: '', superadminNewPass: '',
      snow: Array.from({ length: 28 }, (_, i) => ({ left: (4 + (i * 37) % 92) + '%', top: (6 + (i * 29) % 84) + '%', size: (11 + (i * 7) % 12) + 'px', opacity: 0.2 + ((i * 13) % 30) / 100 })),
    };
  },
  computed: {
    isHostView() { return ['host', 'hostdashboard', 'hostguests', 'hostpoll', 'hostsetup'].includes(this.view); },
    isHostMain() { return ['hostdashboard', 'hostguests', 'hostpoll', 'hostsetup', 'superadmin'].includes(this.view); },
    isGuestView() { return ['who', 'reveal', 'gifts', 'wishlists', 'vote', 'guesthome'].includes(this.view); },
    filteredGuests() { return this.localGuests.filter((g) => matches(g, norm(this.search))); },
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
    if (this.defaultPinActiveLocal && this.isHostView) this.showForcePinModal = true;
    const qs = new URLSearchParams(window.location.search);
    if (qs.get('guest') === '1') {
      const code = qs.get('code');
      if (code) {
        this.api('invite_check', { code }).then(() => this.startGuest(true)).catch(() => this.startGuest());
      } else this.startGuest();
    }
    window.addEventListener('popstate', this.onPopState);
  },
  beforeUnmount() { window.removeEventListener('popstate', this.onPopState); },
  methods: {
    onPopState() {
      const map = { '/': 'home', '/who': 'who', '/reveal': 'reveal', '/gifts': 'gifts', '/wishlists': 'wishlists', '/vote': 'vote', '/guest-home': 'guesthome', '/host/dashboard': 'hostdashboard', '/host/setup': 'hostsetup', '/host/guests': 'hostguests', '/host/poll': 'hostpoll', '/superadmin': 'superadmin' };
      this.view = map[window.location.pathname] || 'home';
      if (this.view === 'hostpoll') this.loadPoll();
      if (this.view === 'superadmin') this.initSuperadmin();
      if (this.defaultPinActiveLocal && this.isHostView) this.showForcePinModal = true;
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
      this.showWishModal = false; this.showQrModal = false; this.showConfirmModal = false; this.showClaimedModal = false; this.showConfirmDialog = false;
      this.showGuestEntryModal = false; this.showHostEntryModal = false; this.showHostPinModal = false;
      this.showGuestModal = false; this.showWishOwnerModal = false; this.showResetPinModal = false; this.showPollModal = false; this.pollMenuId = null; this.showEditPollModal = false; this.fabOpen = false;
      if (this.defaultPinActiveLocal && this.isHostView) this.showForcePinModal = true;
      else if (!this.isHostView) this.showForcePinModal = false;
      if (v === 'hostpoll') this.loadPoll();
      if (v === 'superadmin') this.initSuperadmin();
    },
    async loadGuests() { const d = await this.api('list', {}, 'get'); this.localGuests = d.guests; this.localParty = d.party; this.localBudget = d.budget; this.partyName = d.party; this.partyBudget = d.budget; },
    async loadWishlists() { const d = await this.api('wishlist_list', { me: this.guestMe ? this.guestMe.id : '' }, 'get'); this.localWishBoard = d.board; },
    async loadPoll() { const d = await this.api('poll_get', { me: this.guestMe ? this.guestMe.id : '' }, 'get'); this.localPolls = d.polls; },
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
        const msg = e.message === 'already_claimed' ? 'This name is already locked — no recipient is shown again. Ask the host if you lost it.' : e.message === 'pin_incorrect' ? 'That PIN is incorrect. This name has NOT been locked yet, try again or ask the host.' : 'Could not reveal: ' + e.message;
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
        this.wishText = ''; this.wishLink = ''; this.wishNotes = ''; this.wishImage = ''; this.showWishModal = false;
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
    enterHostWithPin() { this.showHostEntryModal = false; this.showHostPinModal = true; },
    closeHostPinModal() { this.showHostPinModal = false; },
    async submitHostPin() {
      try {
        const res = await this.api('host_status', { host_pin: this.hostPinEntry });
        this.hostList = res.guests; this.hostLoaded = true; this.inviteCode = res.invite_code || this.inviteCode;
        this.hostSignedInLocal = true; this.defaultPinActiveLocal = !!res.default_pin_active;
        this.hostPinEntryError = false; this.showHostPinModal = false;
        if (res.default_pin_active) { this.showForcePinModal = true; this.showToast('Signed in with the default PIN 1234 — set a new 4-digit PIN now.'); }
        else this.showToast('Signed in as host.');
      } catch (e) {
        if (e.message === 'default_pin_change_required') { this.showForcePinModal = true; this.showHostPinModal = false; this.hostSignedInLocal = true; this.defaultPinActiveLocal = true; }
        else if (e.message === 'pin_incorrect') this.hostPinEntryError = true;
        else this.showToast('Host sign-in failed: ' + e.message, false);
      }
    },
    async hostSignOut() {
      try { await this.api('host_logout', {}); } catch (e) { /* local sign-out still proceeds */ }
      this.hostSignedInLocal = false; this.hostLoaded = false; this.hostList = []; this.hostPinEntry = ''; this.hostPin = ''; this.showForcePinModal = false; this.goTo('hostdashboard', '/host/dashboard'); },
    async openDashboard() { await this.loadHost(); this.goTo('hostdashboard', '/host/dashboard'); },
    async loadHost() {
      try {
        const res = await this.api('host_status', { host_pin: this.hostPinEntry });
        this.hostList = res.guests; this.hostLoaded = true; this.inviteCode = res.invite_code || this.inviteCode;
        this.hostSignedInLocal = true; this.defaultPinActiveLocal = !!res.default_pin_active;
        if (res.default_pin_active) this.showForcePinModal = true;
      } catch (e) {
        if (e.message === 'default_pin_change_required') { this.showForcePinModal = true; this.hostSignedInLocal = true; this.defaultPinActiveLocal = true; }
        else if (e.message === 'not_set_up') { this.hostLoaded = false; }
        else if (e.message !== 'host_not_signed_in') this.showToast('Could not load host data: ' + e.message, false);
      }
    },
    openClaimedModal() {
      this.hostMsg = this.hostList.filter((g) => g.claimed).length + ' of ' + this.hostList.length + ' locked';
      this.hostOk = true; this.showClaimedModal = true;
    },
    closeClaimedModal() { this.showClaimedModal = false; },
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
          const res = await this.api('reset_one', { host_pin: this.hostPinEntry, guest_id: id });
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
          await this.api('reset_all', { host_pin: this.hostPinEntry });
          this.hostLoaded = false; this.hostList = []; this.hostSignedInLocal = false; this.inviteCode = '';
          this.setupDoneLocal = false; this.showToast('Everything reset.');
        },
      });
    },
    copyInvite() { navigator.clipboard.writeText(this.inviteCode).then(() => this.showToast('Invite code copied.')).catch(() => this.showToast('Invite code: ' + this.inviteCode)); },
    async regenInvite() {
      this.openConfirmDialog({
        title: 'Generate a new invite code?',
        message: 'The old invite code and QR will stop working. Anyone still using the old link will need the new one.',
        okText: 'Generate new code',
        onOk: async () => { const res = await this.api('invite_regen', { host_pin: this.hostPinEntry }); this.inviteCode = res.invite_code; this.showToast('New invite code generated.'); },
      });
    },
    async doSetup() {
      this.savingSetup = true;
      try {
        const guests = this.guestRows.map((r) => ({ display: r.display, nickname: r.nickname, aliases: r.aliasesText ? r.aliasesText.split(',').map((x) => x.trim()).filter(Boolean) : [], pin: r.pin || '' }));
        const existing = this.setupDoneLocal;
        let res;
        if (existing) {
          res = await this.api('party_update', { host_pin: this.hostPinEntry, party: this.partyName, budget: String(this.partyBudget), date: this.partyDateLocal });
          this.showToast('Party updated.');
        } else {
          res = await this.api('setup', { party: this.partyName, budget: String(this.partyBudget), date: this.partyDateLocal, guests, host_pin: this.hostPin || '' });
          this.setupDoneLocal = true; this.setupDoneLocal = true; this.hostPinEntry = this.hostPin || '1234'; this.hostSignedInLocal = true; this.showToast('Party saved.');
        }
        if (res) { this.localParty = res.party; this.localBudget = res.budget; this.partyDateLocal = res.date || this.partyDateLocal; }
        this.guestRows = [];
        this.openDashboard();
      } catch (e) {
        if (e.message === 'default_pin_change_required') { this.showForcePinModal = true; }
        else if (e.message === 'pin_must_be_4_digits') this.showToast('Host PIN must be exactly 4 digits.', false);
        else if (e.message === 'pin_cannot_be_default') this.showToast('A new host PIN can never be 1234.', false);
        else if (e.message === 'already_set_up') this.showToast('A party is already set up. To start over, reset everything on the dashboard.', false);
        else this.showToast('Could not save: ' + e.message, false);
      } finally { this.savingSetup = false; }
    },
    openResetPinModal() { this.resetPinCurrent = ''; this.resetPinNew = ''; this.resetPinConfirm = ''; this.showResetPinModal = true; },
    closeResetPinModal() { this.showResetPinModal = false; },
    async doResetPin() {
      if (this.resetPinNew.length !== 4 || !/^\d{4}$/.test(this.resetPinNew)) { this.showToast('New PIN must be exactly 4 digits.', false); return; }
      if (this.resetPinNew !== this.resetPinConfirm) { this.showToast('New PIN and confirm PIN do not match.', false); return; }
      if (this.resetPinNew === '1234') { this.showToast('A new host PIN can never be 1234.', false); return; }
      this.resettingPin = true;
      try {
        const res = await this.api('host_pin_reset', { current_pin: this.hostSignedInLocal ? '' : this.resetPinCurrent, new_pin: this.resetPinNew });
        this.hostPinEntry = res.pin; this.defaultPinActiveLocal = false; this.showResetPinModal = false; this.showToast('Host PIN reset.');
      } catch (e) { this.showToast('Could not reset PIN: ' + e.message, false); }
      finally { this.resettingPin = false; }
    },
    async doForcePin() {
      this.forcePinMsg = '';
      if (!/^\d{4}$/.test(this.forcePinNew)) { this.forcePinMsg = 'New PIN must be exactly 4 digits.'; return; }
      if (this.forcePinNew !== this.forcePinConfirm) { this.forcePinMsg = 'New PIN and confirm PIN do not match.'; return; }
      if (this.forcePinNew === '1234') { this.forcePinMsg = 'A new host PIN can never be 1234.'; return; }
      this.resettingForcePin = true;
      try {
        const res = await this.api('host_pin_reset', { current_pin: '1234', new_pin: this.forcePinNew });
        this.hostPinEntry = res.pin; this.hostSignedInLocal = true; this.defaultPinActiveLocal = false; this.showForcePinModal = false; this.showToast('New host PIN set.');
        await this.loadHost();
      } catch (e) { this.forcePinMsg = 'Could not set PIN: ' + e.message; }
      finally { this.resettingForcePin = false; }
    },
    openGuestModal(i) {
      this.guestEditIndex = i;
      if (i >= 0) this.guestForm = { ...this.guestRows[i] };
      else this.guestForm = { display: '', nickname: '', aliasesText: '', pin: '' };
      this.showGuestModal = true;
    },
    closeGuestModal() { this.showGuestModal = false; },
    saveGuestModal() {
      if (!this.guestForm.display.trim()) { this.showToast('Guest name is required.', false); return; }
      if (this.guestForm.pin && !/^\d{4}$/.test(this.guestForm.pin)) { this.showToast('Guest PIN must be exactly 4 digits (or leave it blank).', false); return; }
      if (this.guestEditIndex >= 0) this.guestRows.splice(this.guestEditIndex, 1, { ...this.guestForm });
      else this.guestRows.push({ ...this.guestForm });
      localStorage.setItem('xmasGuestRows', JSON.stringify(this.guestRows));
      this.showGuestModal = false; this.showToast(this.guestEditIndex >= 0 ? 'Guest updated.' : 'Guest added.');
    },
    removeGuestRow(i) { this.guestRows.splice(i, 1); localStorage.setItem('xmasGuestRows', JSON.stringify(this.guestRows)); },
    togglePollMenu(id) { this.pollMenuId = this.pollMenuId === id ? null : id; },
    openPollModal() { this.pollQuestion = 'What should our Christmas theme be?'; this.pollOptionsText = 'Ugly Sweater\nWinter Wonderland\nPajama Party\nRed & Gold'; this.showPollModal = true; },
    closePollModal() { this.showPollModal = false; },
    async setupPoll() {
      const options = this.pollOptionsText.split('\n').map((s) => s.trim()).filter(Boolean);
      if (!this.pollQuestion.trim()) { this.showToast('Question is required.', false); return; }
      if (options.length < 2) { this.showToast('At least 2 options are required.', false); return; }
      try {
        await this.api('poll_setup', { host_pin: this.hostPinEntry, question: this.pollQuestion, options });
        this.showPollModal = false; this.pollQuestion = ''; this.pollOptionsText = ''; await this.loadPoll(); this.showToast('Poll created.');
      } catch (e) { this.showToast('Could not create poll: ' + e.message, false); }
    },
    async setPollOpen(pollId, open) {
      try { const d = await this.api('poll_set_open', { host_pin: this.hostPinEntry, poll_id: pollId, open }); this.localPolls = d.polls; this.pollMenuId = null; this.showToast(open ? 'Poll reopened.' : 'Poll closed.'); }
      catch (e) { this.showToast('Could not update poll: ' + e.message, false); }
    },
    resetPoll(p) {
      this.openConfirmDialog({
        title: 'Delete this poll?', message: 'This poll and all its votes will be deleted. This cannot be undone.', okText: 'Delete poll',
        onOk: async () => { await this.api('poll_reset', { host_pin: this.hostPinEntry, poll_id: p.id }); this.pollMenuId = null; await this.loadPoll(); this.showToast('Poll deleted.'); },
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
      try { const d = await this.api('poll_edit', { host_pin: this.hostPinEntry, poll_id: this.editPollId, question: this.editPollQuestion, options }); this.localPolls = d.polls; this.showEditPollModal = false; this.pollMenuId = null; this.showToast('Poll updated.'); }
      catch (e) { this.showToast('Could not update poll: ' + e.message, false); }
    },
    /* ---------- generic confirm ---------- */
    openConfirmDialog(opts) { this.confirmDialogTitle = opts.title; this.confirmDialogMessage = opts.message; this.confirmDialogOkText = opts.okText || 'OK'; this.confirmDialogCallback = opts.onOk; this.showConfirmDialog = true; },
    closeConfirmDialog() { this.showConfirmDialog = false; },
    async confirmDialogOk() {
      try { if (this.confirmDialogCallback) await this.confirmDialogCallback(); this.showConfirmDialog = false; }
      catch (e) { this.showToast('Could not complete: ' + e.message, false); }
    },
    /* ---------- superadmin ---------- */
    async initSuperadmin() {
      if (this.superadminSignedIn) return;
      try {
        const res = await this.api('superadmin_status', {}, 'get');
        this.superadminSet = !!res.set;
        if (this.superadminSession) { await this.loadSuperadmin(); }
      } catch (e) { /* not set yet */ }
    },
    async loadSuperadmin() {
      const res = await this.api('superadmin_get', { session: this.superadminSession });
      this.superadminSignedIn = true;
      this.superadminParty = res.party; this.superadminBudget = res.budget; this.superadminDate = res.date; this.superadminInvite = res.invite_code;
      this.superadminGuests = res.guests; this.superadminPolls = res.polls;
    },
    async superadminSetup() {
      try {
        const res = await this.api('superadmin_setup', { username: this.superadminUser, password: this.superadminPass });
        this.superadminSession = res.session; this.showToast('Superadmin account created.'); await this.loadSuperadmin();
      } catch (e) { this.showToast('Could not set up superadmin: ' + e.message, false); }
    },
    async superadminLogin() {
      try {
        const res = await this.api('superadmin_login', { username: this.superadminUser, password: this.superadminPass });
        this.superadminSession = res.session; await this.loadSuperadmin();
      } catch (e) { this.showToast('Superadmin sign-in failed: ' + e.message, false); }
    },
    async superadminLogout() {
      try { await this.api('superadmin_logout', { session: this.superadminSession }); } catch (e) { /* ignore */ }
      this.superadminSignedIn = false; this.superadminSession = ''; this.superadminPass = '';
    },
    async saveSuperadminParty() {
      try { await this.api('superadmin_party_update', { session: this.superadminSession, party: this.superadminParty, budget: String(this.superadminBudget), date: this.superadminDate }); this.showToast('Party details updated.'); }
      catch (e) { this.showToast('Could not update party: ' + e.message, false); }
    },
    async regenSuperadminInvite() {
      try { const res = await this.api('superadmin_invite_regen', { session: this.superadminSession }); this.superadminInvite = res.invite_code; this.showToast('New invite code generated.'); }
      catch (e) { this.showToast('Could not generate code: ' + e.message, false); }
    },
    async setSuperadminHostPin() {
      try { await this.api('superadmin_host_pin_reset', { session: this.superadminSession, new_pin: this.superadminNewPin }); this.showToast('Host PIN set.'); }
      catch (e) { this.showToast('Could not set host PIN: ' + e.message, false); }
    },
    async superadminResetOne(g) {
      try { await this.api('superadmin_reset_one', { session: this.superadminSession, guest_id: g.id }); await this.loadSuperadmin(); this.showToast('Claim reset.'); }
      catch (e) { this.showToast('Could not reset claim: ' + e.message, false); }
    },
    async superadminUnclaimAll() {
      try { await this.api('superadmin_reset_claims', { session: this.superadminSession }); await this.loadSuperadmin(); this.showToast('All claims cleared.'); }
      catch (e) { this.showToast('Could not unclaim: ' + e.message, false); }
    },
    async superadminRedraw() {
      try { await this.api('superadmin_redraw', { session: this.superadminSession }); await this.loadSuperadmin(); this.showToast('Draw redone.'); }
      catch (e) { this.showToast(e.message === 'redraw_blocked_claimed' ? 'Cannot redraw while any name is claimed — reset all claims first.' : 'Could not redraw: ' + e.message, false); }
    },
    async superadminClearWishlists() {
      try { await this.api('superadmin_clear_wishlists', { session: this.superadminSession }); this.showToast('All wishlists cleared.'); }
      catch (e) { this.showToast('Could not clear wishlists: ' + e.message, false); }
    },
    async superadminPollOpen(p, open) {
      try { await this.api('superadmin_poll_set_open', { session: this.superadminSession, poll_id: p.id, open }); await this.loadSuperadmin(); this.showToast(open ? 'Poll reopened.' : 'Poll closed.'); }
      catch (e) { this.showToast('Could not update poll: ' + e.message, false); }
    },
    async superadminPollDelete(p) {
      try { await this.api('superadmin_poll_delete', { session: this.superadminSession, poll_id: p.id }); this.superadminPolls = this.superadminPolls.filter((x) => x.id !== p.id); this.showToast('Poll deleted.'); }
      catch (e) { this.showToast('Could not delete poll: ' + e.message, false); }
    },
    async changeSuperadminPassword() {
      try { await this.api('superadmin_change_password', { session: this.superadminSession, new_password: this.superadminNewPass }); this.showToast('Password changed.'); }
      catch (e) { this.showToast('Could not change password: ' + e.message, false); }
    },
    async superadminResetAll() {
      this.openConfirmDialog({
        title: 'Reset everything?', message: 'This clears the whole party. The superadmin account survives. This cannot be undone.', okText: 'Reset everything',
        onOk: async () => { await this.api('superadmin_reset_all', { session: this.superadminSession }); this.setupDoneLocal = false; this.superadminParty = ''; this.superadminGuests = []; this.superadminPolls = []; this.superadminInvite = ''; this.showToast('Everything reset.'); },
      });
    },
  },
};
</script>

<style>
body { background: #000; color: #fafafa; font-family: Inter, system-ui, sans-serif; }
a { color: #34d399; }
</style>
