import type {AddonEntrypoint} from '@/loadAddons.js';

export const addon: AddonEntrypoint = async (context) => ({
    commands: async (program) => {
        program
            .command('test')
            .description('Execute phpunit tests inside the app container')
            .option('-c, --coverage', 'Generate code coverage report')
            .allowExcessArguments(true)
            .action(async (options, command) => {
                let cmd = ['composer', 'run', 'test:unit'];
                if (options.coverage) {
                    cmd = ['composer', 'run', 'test:unit:coverage'];
                    if (command.args.length > 0) {
                        console.warn('Warning: Code coverage option is enabled, but additional arguments are provided. The code coverage command may not support additional arguments.');
                        command.args = [];
                    }
                }
                await context.docker.executeCommandInService('app', [...cmd, ...command.args], {interactive: true});
            });
    }
});
