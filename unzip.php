<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام تنفيذ الأوامر - runcmd</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #bdc3c7;
            font-size: 14px;
        }
        
        .terminal {
            background: #1e1e1e;
            padding: 20px;
            font-family: 'Courier New', monospace;
            min-height: 300px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .command-line {
            margin: 5px 0;
            color: #00ff00;
        }
        
        .output-line {
            margin: 5px 0 10px 20px;
            color: #ffffff;
            white-space: pre-wrap;
        }
        
        .error-line {
            color: #ff6b6b;
            margin: 5px 0 10px 20px;
        }
        
        .input-area {
            background: #34495e;
            padding: 20px;
            display: flex;
            gap: 10px;
        }
        
        .prompt {
            color: #00ff00;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        
        .command-input {
            flex: 1;
            background: #2c3e50;
            border: 1px solid #00ff00;
            color: #00ff00;
            padding: 10px 15px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            border-radius: 5px;
            outline: none;
        }
        
        .command-input:focus {
            border-color: #fff;
            box-shadow: 0 0 10px rgba(0,255,0,0.3);
        }
        
        .execute-btn {
            background: #00ff00;
            color: #1e1e1e;
            border: none;
            padding: 10px 25px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .execute-btn:hover {
            background: #00cc00;
            transform: scale(1.05);
        }
        
        .buttons-area {
            padding: 20px;
            background: #ecf0f1;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .cmd-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .cmd-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .clear-btn {
            background: #e74c3c;
        }
        
        .clear-btn:hover {
            background: #c0392b;
        }
        
        .help-btn {
            background: #f39c12;
        }
        
        .help-btn:hover {
            background: #e67e22;
        }
        
        .footer {
            background: #2c3e50;
            color: #bdc3c7;
            padding: 15px;
            text-align: center;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚡ نظام تنفيذ الأوامر - runcmd</h1>
            <p>محاكاة لتنفيذ أوامر SSH والضغط على الخادم</p>
        </div>
        
        <div class="terminal" id="terminal">
            <div class="command-line">$ <span style="color: #fff;">system info</span></div>
            <div class="output-line">> نظام محاكاة الأوامر جاهز</div>
            <div class="output-line">> يمكنك تجربة أوامر: zip, unzip, ls, pwd, help</div>
        </div>
        
        <div class="input-area">
            <span class="prompt">$&nbsp;</span>
            <input type="text" class="command-input" id="commandInput" placeholder="اكتب الأمر هنا...">
            <button class="execute-btn" onclick="executeCommand()">تشغيل</button>
        </div>
        
        <div class="buttons-area">
            <button class="cmd-btn" onclick="quickCommand('unzip mysite.zip')">📦 unzip mysite.zip</button>
            <button class="cmd-btn" onclick="quickCommand('tar -xzvf backup.tar.gz')">🗜️ tar -xzvf backup.tar.gz</button>
            <button class="cmd-btn" onclick="quickCommand('ls -la')">📁 ls -la</button>
            <button class="cmd-btn" onclick="quickCommand('pwd')">📍 pwd</button>
            <button class="cmd-btn" onclick="quickCommand('mkdir newfolder')">📂 mkdir newfolder</button>
            <button class="cmd-btn help-btn" onclick="quickCommand('help')">❓ help</button>
            <button class="cmd-btn clear-btn" onclick="clearTerminal()">🗑️ مسح</button>
        </div>
        
        <div class="footer">
            <p>⚠️ هذه محاكاة تعليمية - الأوامر لا تنفذ على خادم حقيقي</p>
            <p style="margin-top: 5px;">للاستخدام الحقيقي: استخدم PuTTY مع المنفذ 22</p>
        </div>
    </div>

    <script>
        const terminal = document.getElementById('terminal');
        const commandInput = document.getElementById('commandInput');
        
        // قاعدة بيانات الأوامر المدعومة
        const commands = {
            'help': {
                description: 'عرض قائمة الأوامر المتاحة',
                execute: () => {
                    return `الأوامر المتاحة:
  help     - عرض هذه القائمة
  ls       - عرض محتويات المجلد الحالي
  pwd      - عرض المسار الحالي
  mkdir    - إنشاء مجلد جديد
  unzip    - فك ضغط ملف zip
  tar      - فك ضغط ملف tar.gz
  zip      - ضغط ملفات
  clear    - مسح الشاشة
  date     - عرض التاريخ والوقت
  whoami   - عرض اسم المستخدم
  df -h    - عرض مساحة القرص
  free -m  - عرض الذاكرة المتاحة
  echo     - طباعة نص
  runcmd   - محاكاة تنفيذ الأوامر`;
                }
            },
            'ls': {
                description: 'عرض الملفات',
                execute: (args) => {
                    const files = [
                        'mysite.zip',
                        'backup.tar.gz',
                        'index.html',
                        'style.css',
                        'script.js',
                        'images/',
                        'public_html/',
                        'README.txt'
                    ];
                    
                    if (args.includes('-la')) {
                        return `total 64
drwxr-xr-x 5 user user 4096 Mar 10 10:30 .
drwxr-xr-x 3 user user 4096 Mar 10 10:30 ..
-rw-r--r-- 1 user user 20480 Mar 10 09:15 mysite.zip
-rw-r--r-- 1 user user 10240 Mar 10 09:20 backup.tar.gz
-rw-r--r-- 1 user user  1024 Mar 10 10:30 index.html
-rw-r--r-- 1 user user  2048 Mar 10 10:30 style.css
-rw-r--r-- 1 user user  3072 Mar 10 10:30 script.js
drwxr-xr-x 2 user user  4096 Mar 10 10:30 images/
drwxr-xr-x 2 user user  4096 Mar 10 10:30 public_html/
-rw-r--r-- 1 user user   512 Mar 10 10:30 README.txt`;
                    } else {
                        return files.join('  ');
                    }
                }
            },
            'pwd': {
                description: 'المسار الحالي',
                execute: () => '/home/user/public_html'
            },
            'whoami': {
                description: 'اسم المستخدم',
                execute: () => 'user@server'
            },
            'date': {
                description: 'التاريخ والوقت',
                execute: () => new Date().toString()
            },
            'df -h': {
                description: 'مساحة القرص',
                execute: () => `Filesystem      Size  Used Avail Use% Mounted on
/dev/sda1        50G   25G   25G  50% /
tmpfs           2.0G  100M  1.9G   5% /dev/shm`
            },
            'free -m': {
                description: 'الذاكرة',
                execute: () => `              total        used        free      shared  buff/cache
Mem:           2000        1200         300         100         400
Swap:          1000         200         800`
            },
            'unzip': {
                description: 'فك ضغط zip',
                execute: (args) => {
                    if (args.includes('mysite.zip')) {
                        return `Archive:  mysite.zip
  inflating: index.html
  inflating: style.css
  inflating: script.js
  creating: images/
  inflating: README.txt
تم فك الضغط بنجاح ✅`;
                    }
                    return `أمر unzip: تم فك الضغط
ملاحظة: هذا محاكاة - في الواقع استخدم: unzip filename.zip`;
                }
            },
            'tar': {
                description: 'فك ضغط tar',
                execute: (args) => {
                    if (args.includes('-xzvf') && args.includes('backup.tar.gz')) {
                        return `backup/
backup/file1.txt
backup/file2.txt
backup/config.json
تم فك الضغط بنجاح ✅`;
                    }
                    return `أمر tar: تم فك الضغط
استخدم: tar -xzvf filename.tar.gz`;
                }
            },
            'mkdir': {
                description: 'إنشاء مجلد',
                execute: (args) => {
                    const folderName = args.split(' ')[1] || 'newfolder';
                    return `تم إنشاء المجلد: ${folderName} ✅`;
                }
            },
            'echo': {
                description: 'طباعة نص',
                execute: (args) => args.replace('echo ', '')
            },
            'runcmd': {
                description: 'محاكاة runcmd',
                execute: (args) => {
                    return `تنفيذ الأمر: ${args}
✅ تم التنفيذ بنجاح عبر runcmd`;
                }
            }
        };
        
        function executeCommand() {
            const command = commandInput.value.trim();
            if (!command) return;
            
            // عرض الأمر في التيرمنال
            addToTerminal('command', command);
            
            // تنفيذ الأمر
            const output = processCommand(command);
            addToTerminal('output', output);
            
            // مسح حقل الإدخال
            commandInput.value = '';
            
            // التمرير للأسفل
            terminal.scrollTop = terminal.scrollHeight;
        }
        
        function processCommand(cmd) {
            const lowerCmd = cmd.toLowerCase();
            
            // أمر clear
            if (lowerCmd === 'clear' || lowerCmd === 'cls') {
                clearTerminal();
                return '';
            }
            
            // البحث عن الأمر المناسب
            for (const [key, value] of Object.entries(commands)) {
                if (lowerCmd.startsWith(key)) {
                    const args = cmd.substring(key.length).trim();
                    return value.execute(args);
                }
            }
            
            // أمر غير معروف
            return `❌ أمر غير معروف: "${cmd}"
اكتب 'help' لعرض قائمة الأوامر المتاحة`;
        }
        
        function addToTerminal(type, content) {
            const line = document.createElement('div');
            
            if (type === 'command') {
                line.className = 'command-line';
                line.innerHTML = `$ <span style="color: #fff;">${content}</span>`;
            } else {
                if (content.startsWith('❌')) {
                    line.className = 'error-line';
                } else {
                    line.className = 'output-line';
                }
                line.textContent = content;
            }
            
            terminal.appendChild(line);
        }
        
        function quickCommand(cmd) {
            commandInput.value = cmd;
            executeCommand();
        }
        
        function clearTerminal() {
            terminal.innerHTML = `
                <div class="command-line">$ <span style="color: #fff;">system clear</span></div>
                <div class="output-line">> تم مسح الشاشة</div>
                <div class="output-line">> نظام محاكاة الأوامر جاهز</div>
            `;
        }
        
        // تنفيذ الأمر بالضغط على Enter
        commandInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                executeCommand();
            }
        });
        
        // إضافة بعض الأوامر الترحيبية
        window.onload = () => {
            addToTerminal('output', 'مرحباً بك في نظام runcmd');
            addToTerminal('output', 'اكتب "help" لعرض جميع الأوامر');
        };
    </script>
</body>
</html>