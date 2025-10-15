import type {Context} from '@/Context.js';
import fs from 'node:fs';

export async function welcome(context: Context) {
    const {paths, ui, pkg} = context;

    if (fs.existsSync(paths.envFileHashPath)) {
        return;
    }

    console.log(ui.renderWelcome(pkg));
    await pressAnyKey();
}

function pressAnyKey() {
    console.log('Press any key to continue...');

    return new Promise((resolve) => {
        const handler = (buffer: any) => {
            process.stdin.removeListener('data', handler);
            process.stdin.setRawMode(false);
            process.stdin.pause();
            process.stdout.write('\n');

            const bytes = Array.from(buffer);
            if (bytes.length && bytes[0] === 3) {
                process.exit(0);
            }
            process.nextTick(resolve);
        };

        process.stdin.resume();
        process.stdin.setRawMode(true);
        process.stdin.once('data', handler);
    });
}
