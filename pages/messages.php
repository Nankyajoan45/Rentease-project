<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Messages';

$db = getDB();
$threadId = (int)($_GET['thread'] ?? 0);

// Inbox conversations
$inbox = $db->prepare("
    SELECT m.*,
           CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_id,
           u.name AS other_name
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    WHERE (m.sender_id = ? OR m.receiver_id = ?) AND m.parent_id IS NULL
    ORDER BY m.created_at DESC LIMIT 50
");
$inbox->execute([$user['id'],$user['id'],$user['id'],$user['id']]);
$inbox = $inbox->fetchAll();

// Thread
$thread = [];
$threadOther = null;
if ($threadId) {
    $tStmt = $db->prepare("
        SELECT m.*, u.name AS sender_name
        FROM messages m JOIN users u ON u.id = m.sender_id
        WHERE m.id = ? OR m.parent_id = ?
        ORDER BY m.created_at ASC
    ");
    $tStmt->execute([$threadId, $threadId]);
    $thread = $tStmt->fetchAll();
    $db->prepare("UPDATE messages SET is_read=1 WHERE (id=? OR parent_id=?) AND receiver_id=?")
       ->execute([$threadId,$threadId,$user['id']]);
    if ($thread) {
        $first = $thread[0];
        $otherId = $first['sender_id'] == $user['id']
            ? $db->query("SELECT receiver_id FROM messages WHERE id=$threadId")->fetchColumn()
            : $first['sender_id'];
        $threadOther = $db->query("SELECT id,name,role FROM users WHERE id=$otherId")->fetch();
    }
}

// Users list for new message
$users = $db->prepare("SELECT id, name, role FROM users WHERE id != ? AND is_active=1 ORDER BY name LIMIT 100");
$users->execute([$user['id']]); $users = $users->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <h1 class="font-display text-3xl text-white">Messages</h1>
        <p class="text-blue-200 mt-1">Communicate with landlords and tenants</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" style="height:600px">
        <div class="flex h-full">

            <!-- Inbox List -->
            <div class="w-72 border-r border-slate-100 flex flex-col flex-shrink-0 <?= $threadId ? 'hidden sm:flex' : 'flex' ?>">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800 text-sm">Inbox</h2>
                    <button onclick="document.getElementById('newMsgModal').classList.remove('hidden')"
                            class="w-8 h-8 bg-primary-100 text-primary-700 rounded-lg flex items-center justify-center hover:bg-primary-200 transition-colors">
                        <i class="fas fa-plus text-xs"></i>
                    </button>
                </div>
                <div class="overflow-y-auto flex-1">
                    <?php if (empty($inbox)): ?>
                    <div class="p-8 text-center text-slate-400 text-sm">
                        <i class="fas fa-inbox text-3xl block mb-3 opacity-30"></i>
                        No messages yet
                    </div>
                    <?php else: ?>
                    <?php foreach ($inbox as $m): ?>
                    <a href="?thread=<?= $m['id'] ?>"
                       class="flex items-start gap-3 p-4 border-b border-slate-50 hover:bg-slate-50 transition-colors <?= $threadId==$m['id'] ? 'bg-blue-50 border-l-2 border-l-primary-500' : '' ?>">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                            <?= strtoupper(substr($m['other_name'],0,1)) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <p class="text-sm font-semibold text-slate-800 truncate"><?= sanitize($m['other_name']) ?></p>
                                <?php if (!$m['is_read'] && $m['receiver_id']==$user['id']): ?>
                                <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-500 truncate font-medium"><?= sanitize($m['subject']) ?></p>
                            <p class="text-xs text-slate-400 truncate"><?= sanitize(substr($m['body'],0,50)) ?></p>
                            <p class="text-xs text-slate-300 mt-0.5"><?= timeAgo($m['created_at']) ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thread View -->
            <div class="flex-1 flex flex-col <?= !$threadId ? 'hidden sm:flex' : 'flex' ?>">
                <?php if ($threadId && !empty($thread)): ?>
                <div class="p-4 border-b border-slate-100 flex items-center gap-3">
                    <a href="<?= APP_URL ?>/pages/messages.php" class="sm:hidden p-2 text-slate-400 hover:text-slate-600 mr-1">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-sm font-bold">
                        <?= strtoupper(substr($threadOther['name']??'U',0,1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800 text-sm"><?= sanitize($threadOther['name']??'Unknown') ?></p>
                        <p class="text-xs text-slate-500 capitalize"><?= sanitize($threadOther['role']??'') ?></p>
                    </div>
                </div>
                <div id="chatArea" class="flex-1 overflow-y-auto p-5 space-y-4">
                    <?php foreach ($thread as $m): ?>
                    <div class="flex <?= $m['sender_id']==$user['id'] ? 'justify-end' : 'justify-start' ?>">
                        <div class="max-w-xs sm:max-w-sm">
                            <div class="<?= $m['sender_id']==$user['id'] ? 'message-bubble-out' : 'message-bubble-in' ?>">
                                <?= nl2br(sanitize($m['body'])) ?>
                            </div>
                            <p class="text-xs text-slate-400 mt-1 <?= $m['sender_id']==$user['id'] ? 'text-right' : 'text-left' ?>">
                                <?= timeAgo($m['created_at']) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-4 border-t border-slate-100">
                    <div class="flex gap-3">
                        <textarea id="replyText" rows="2"
                                  class="flex-1 form-textarea text-sm resize-none py-2.5"
                                  placeholder="Type a reply…"
                                  onkeydown="if(event.ctrlKey&&event.key==='Enter')sendReply()"></textarea>
                        <button onclick="sendReply()"
                                class="btn-primary px-4 py-2 self-end text-sm shrink-0">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Ctrl + Enter to send</p>
                </div>
                <?php else: ?>
                <div class="flex-1 flex items-center justify-center">
                    <div class="text-center text-slate-400">
                        <i class="fas fa-comments text-5xl mb-4 block opacity-20"></i>
                        <p class="font-medium text-slate-500">Select a conversation</p>
                        <p class="text-sm mt-1">or start a new one</p>
                        <button onclick="document.getElementById('newMsgModal').classList.remove('hidden')"
                                class="btn-primary mt-4 text-sm">
                            <i class="fas fa-plus"></i> New Message
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div id="newMsgModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold text-slate-800">New Message</h3>
            <button onclick="document.getElementById('newMsgModal').classList.add('hidden')"
                    class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 hover:bg-slate-200">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="form-label">To</label>
                <select id="newReceiver" class="form-select">
                    <option value="">Select recipient…</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?> (<?= ucfirst($u['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Subject</label>
                <input type="text" id="newSubject" class="form-input" placeholder="Subject…">
            </div>
            <div>
                <label class="form-label">Message</label>
                <textarea id="newBody" class="form-textarea" rows="4" placeholder="Write your message…"></textarea>
            </div>
            <div class="flex gap-3 pt-1">
                <button onclick="document.getElementById('newMsgModal').classList.add('hidden')"
                        class="flex-1 btn-outline py-2.5 text-sm">Cancel</button>
                <button onclick="sendNew()" class="flex-1 btn-primary justify-center py-2.5 text-sm">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';
const THREAD_ID = <?= $threadId ?: 0 ?>;
const RECEIVER_ID = <?= $threadOther['id'] ?? 0 ?>;

// Scroll to bottom
const chat = document.getElementById('chatArea');
if (chat) chat.scrollTop = chat.scrollHeight;

async function sendReply() {
    const body = document.getElementById('replyText').value.trim();
    if (!body) return;
    const res = await fetch('<?= APP_URL ?>/api/messages.php?action=send', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ receiver_id: RECEIVER_ID, body, parent_id: THREAD_ID, subject: 'Reply' })
    });
    const data = await res.json();
    if (data.success) {
        // Append bubble
        const div = document.createElement('div');
        div.className = 'flex justify-end';
        div.innerHTML = `<div class="max-w-xs"><div class="message-bubble-out">${body.replace(/\n/g,'<br>')}</div><p class="text-xs text-slate-400 mt-1 text-right">just now</p></div>`;
        chat.appendChild(div);
        chat.scrollTop = chat.scrollHeight;
        document.getElementById('replyText').value = '';
    } else {
        showToast(data.error || 'Failed to send', 'error');
    }
}

async function sendNew() {
    const receiverId = document.getElementById('newReceiver').value;
    const subject   = document.getElementById('newSubject').value.trim() || 'New Message';
    const body      = document.getElementById('newBody').value.trim();
    if (!receiverId || !body) return showToast('Fill in all fields', 'warning');
    const res = await fetch('<?= APP_URL ?>/api/messages.php?action=send', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ receiver_id: parseInt(receiverId), subject, body })
    });
    const data = await res.json();
    if (data.success) {
        showToast('Message sent!');
        document.getElementById('newMsgModal').classList.add('hidden');
        setTimeout(() => location.href = '<?= APP_URL ?>/pages/messages.php?thread='+data.id, 500);
    } else {
        showToast(data.error || 'Send failed', 'error');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
