import type {AddonEntrypoint} from '@/loadAddons.js';
import {EnvFileMigrator} from '@/env/EnvFileMigrator.js';

export const addon: AddonEntrypoint = async (context) => ({
    commands: async (program) => {
        program
            .command('env:reset')
            .description('Resets your current .env file back to the default definition')
            .action(async () => {
                await ((new EnvFileMigrator(context.events, context.paths)).setForced(true).migrate(context.env));
            });

        program
            .command('bin:npm')
            .description('Runs an npm command for the bin/env cli tool. This is useful for installing dependencies for custom cli commands.')
            .action(async () => {
                throw new Error('This command will never reach here, it is handled by the bin/env shell script.');
            });

        program
            .command('bin:tsc')
            .description('Runs the TypeScript type checker for the bin/env cli tool. You don\'t need to do this, but you may use it if you want to check the types of your custom cli commands.')
            .action(async () => {
                throw new Error('This command will never reach here, it is handled by the bin/env shell script.');
            });
    }
});
