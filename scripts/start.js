const { spawn } = require('child_process');
const fs = require('fs');
const https = require('https');
const path = require('path');

// If this folder is a junction, chdir to the real path so Expo's public-folder
// guard does not treat bundled assets as outside the project (HTTP 500 in Expo Go).
const projectRoot = fs.realpathSync(process.cwd());
process.chdir(projectRoot);

// PHP backend uses `public/`; Expo must use a separate in-project static folder.
const expoStatic = path.join(projectRoot, 'expo-static');
if (!fs.existsSync(expoStatic)) {
  fs.mkdirSync(expoStatic, { recursive: true });
}
process.env.EXPO_PUBLIC_FOLDER = 'expo-static';

const NGROK_DOMAIN = 'concinnous-unobliging-max.ngrok-free.dev';

function spawnDetached(command, args, extra = {}) {
  const child = spawn(command, args, {
    detached: true,
    stdio: 'ignore',
    windowsHide: true,
    ...extra,
  });
  child.unref();
  return child;
}

function startXamppIfPresent() {
  const roots = ['C:\\xamppss', 'C:\\xampp'];
  for (const root of roots) {
    for (const bat of ['apache_start.bat', 'mysql_start.bat']) {
      const file = path.join(root, bat);
      if (fs.existsSync(file)) {
        spawnDetached('cmd.exe', ['/c', file], { cwd: root });
      }
    }
  }
}

function tunnelUp() {
  return new Promise((resolve) => {
    const req = https.get(`https://${NGROK_DOMAIN}/GuideMate/public/`, { timeout: 4000 }, (res) => {
      res.resume();
      resolve(typeof res.statusCode === 'number' && res.statusCode < 500);
    });
    req.on('error', () => resolve(false));
    req.on('timeout', () => {
      req.destroy();
      resolve(false);
    });
  });
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function ensureGoogleTunnel() {
  if (process.platform !== 'win32') {
    return;
  }
  startXamppIfPresent();
  if (await tunnelUp()) {
    console.log('[GuideMate] Google sign-in tunnel is online.');
    return;
  }
  const bat = path.join(projectRoot, 'start-ngrok.bat');
  if (!fs.existsSync(bat)) {
    console.warn('[GuideMate] start-ngrok.bat is missing — Google sign-in needs the public tunnel.');
    return;
  }
  console.log('[GuideMate] Starting Google sign-in tunnel (ngrok). Keep Apache running in XAMPP.');
  spawn('cmd.exe', ['/c', 'start', 'GuideMate Google tunnel', '/MIN', bat], {
    cwd: projectRoot,
    detached: true,
    stdio: 'ignore',
  }).unref();

  for (let i = 0; i < 15; i++) {
    await sleep(1500);
    if (await tunnelUp()) {
      console.log('[GuideMate] Google sign-in tunnel is ready.');
      return;
    }
  }
  console.warn(
    '[GuideMate] Tunnel not reachable yet. Turn on XAMPP (Apache + MySQL) and leave the "GuideMate Google tunnel" window open so Google login works after a restart.'
  );
}

function startExpo() {
  const expoPath = path.join(
    projectRoot,
    'node_modules',
    '.bin',
    process.platform === 'win32' ? 'expo.cmd' : 'expo'
  );
  const extraArgs = process.argv.slice(2);
  const expoArgs = ['start', ...extraArgs];

  const child = process.platform === 'win32'
    ? spawn('cmd.exe', ['/c', expoPath, ...expoArgs], {
        cwd: projectRoot,
        stdio: 'inherit',
      })
    : spawn(expoPath, expoArgs, {
        cwd: projectRoot,
        stdio: 'inherit',
      });

  child.on('exit', (code) => {
    process.exit(code ?? 0);
  });
  child.on('error', (error) => {
    console.error(error);
    process.exit(1);
  });
}

ensureGoogleTunnel()
  .catch((error) => {
    console.warn('[GuideMate] Could not start the Google tunnel:', error.message);
  })
  .finally(() => {
    startExpo();
  });
