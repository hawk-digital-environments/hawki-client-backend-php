import type {Paths} from '../Paths.js';
import fs from 'node:fs';
import {confirm} from '@inquirer/prompts';
import * as crypto from 'node:crypto';
import type {EventBus} from '../EventBus.js';
import chalk from 'chalk';

export async function ensureEnvFileExists(events: EventBus, paths: Paths): Promise<void> {
    if (!fs.existsSync(paths.envFilePath)) {
        await events.trigger('env:initialize:before', {
            envFilePath: paths.envFilePath,
            templatePath: paths.envFileTemplatePath
        });

        if (!fs.existsSync(paths.envFileTemplatePath)) {
            if (!await confirm({
                message: `The .env file is missing, as there is no template to copy from, should I create an empty .env file?`
            })) {
                throw new Error('You can not continue without an .env file, sorry');
            }

            fs.writeFileSync(paths.envFilePath, '', 'utf-8');
            return;
        }

        if (!await confirm({
            message: `The ${chalk.bold('.env')} file is currently missing, should I create one for you based on the template: "${chalk.bold(paths.envFileTemplatePath)}"?`
        })) {
            throw new Error('You can not continue without an .env file, sorry');
        }

        fs.copyFileSync(paths.envFileTemplatePath, paths.envFilePath);
    }
}

function getCurrentEnvFileHash(paths: Paths): string {
    const envFileContent = fs.readFileSync(paths.envFilePath, 'utf-8');
    return crypto.createHash('sha256').update(envFileContent).digest('hex');
}

function getStoredEnvFileHash(paths: Paths): string {
    if (!fs.existsSync(paths.envFileHashPath)) {
        return '-1';
    }

    return fs.readFileSync(paths.envFileHashPath, 'utf-8');
}

export function updateEnvFileHash(paths: Paths): void {
    fs.writeFileSync(paths.envFileHashPath, getCurrentEnvFileHash(paths), 'utf-8');
}

export function envFileHashChanged(paths: Paths): boolean {
    const currentHash = getCurrentEnvFileHash(paths);
    const storedHash = getStoredEnvFileHash(paths);
    return currentHash !== storedHash;
}
