import type {AddonConfig} from '@/loadAddons.js';
import path from 'node:path';
import type {Paths} from '@/Paths.js';

export const defineDockerEnv: (paths: Paths) => AddonConfig['env'] = (paths) => async (definition) => {
    definition
        .define('PROJECT_NAME', {
            remove: 'it has been deprecated in favor of COMPOSE_PROJECT_NAME'
        })
        .define('COMPOSE_PROJECT_NAME', {
            message: 'Please enter a project name for docker compose',
            help: 'You need to define a project name, which can be used for your docker containers and generated urls.',
            default: async (templateValue, replacedValue) => {
                if (!!replacedValue && replacedValue !== templateValue) {
                    console.log('replacedValue', replacedValue, 'templateValue', templateValue);
                    return replacedValue;
                }

                const projectPath = paths.projectDir;
                let projectName = path.basename(projectPath);
                if (projectName.length < 5) {
                    const parentDir = path.dirname(projectPath);
                    projectName = path.basename(parentDir) + '-' + projectName;
                }
                // Ensure only valid characters are used
                return projectName.toLowerCase().replace(/[^a-z0-9-]/g, '-');
            },
            validate: (input) => {
                return input.length > 0 && input.match(/^[a-z0-9-]+$/) ? true : 'The project name must only contain lowercase letters, numbers and dashes';
            },
            replaces: 'PROJECT_NAME'
        });
};
