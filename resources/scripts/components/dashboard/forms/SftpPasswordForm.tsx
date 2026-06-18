import { Actions, useStoreActions } from 'easy-peasy';
import { useEffect, useState } from 'react';

import FlashMessageRender from '@/components/FlashMessageRender';
import ActionButton from '@/components/elements/ActionButton';
import Code from '@/components/elements/Code';
import CopyOnClick from '@/components/elements/CopyOnClick';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';

import { httpErrorToHuman } from '@/api/http';
import {
    GeneratedSftpPassword,
    SftpPasswordStatus,
    generateSftpPassword,
    getSftpPasswordStatus,
    revokeSftpPassword,
} from '@/api/account/sftpPassword';

import { ApplicationStore } from '@/state';

interface Props {
    showIntro?: boolean;
}

const SftpPasswordForm = ({ showIntro = true }: Props) => {
    const [loading, setLoading] = useState(false);
    const [status, setStatus] = useState<SftpPasswordStatus | null>(null);
    const [generated, setGenerated] = useState<GeneratedSftpPassword | null>(null);

    const { clearFlashes, addFlash } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    useEffect(() => {
        getSftpPasswordStatus()
            .then(setStatus)
            .catch(() => {
                // Non-fatal: the section simply shows no active password.
            });
    }, []);

    const doGenerate = () => {
        setLoading(true);
        clearFlashes('account:sftp-password');

        generateSftpPassword()
            .then((data) => {
                setGenerated(data);
                setStatus({ active: true, expires_at: data.expires_at });
            })
            .catch((error) =>
                addFlash({
                    type: 'error',
                    key: 'account:sftp-password',
                    title: 'Error',
                    message: httpErrorToHuman(error),
                }),
            )
            .then(() => setLoading(false));
    };

    const doRevoke = () => {
        setLoading(true);
        clearFlashes('account:sftp-password');

        revokeSftpPassword()
            .then(() => {
                setGenerated(null);
                setStatus({ active: false, expires_at: null });
            })
            .catch((error) =>
                addFlash({
                    type: 'error',
                    key: 'account:sftp-password',
                    title: 'Error',
                    message: httpErrorToHuman(error),
                }),
            )
            .then(() => setLoading(false));
    };

    return (
        <div className='relative'>
            <SpinnerOverlay size={'large'} visible={loading} />
            <FlashMessageRender byKey={'account:sftp-password'} />
            {showIntro && (
                <p className='text-sm mb-4 text-zinc-300'>
                    Generate a temporary password for SFTP access. This is useful if you sign in through single
                    sign-on and have no account password to use with SFTP. The password is shown only once and
                    expires automatically.
                </p>
            )}

            {generated && (
                <div className='mb-4 space-y-2'>
                    <p className='text-sm text-zinc-300'>Copy this password now — it will not be shown again:</p>
                    <CopyOnClick text={generated.password} showInNotification={false}>
                        <Code>{generated.password}</Code>
                    </CopyOnClick>
                    <p className='text-xs text-zinc-400'>
                        Expires {new Date(generated.expires_at).toLocaleString()}.
                    </p>
                </div>
            )}

            {!generated && status?.active && status.expires_at && (
                <p className='text-sm mb-4 text-zinc-400'>
                    An active temporary SFTP password expires {new Date(status.expires_at).toLocaleString()}.
                </p>
            )}

            <div className='flex gap-3'>
                <ActionButton variant='primary' onClick={doGenerate} disabled={loading}>
                    {status?.active ? 'Regenerate Password' : 'Generate Password'}
                </ActionButton>
                {status?.active && (
                    <ActionButton variant='secondary' onClick={doRevoke} disabled={loading}>
                        Revoke
                    </ActionButton>
                )}
            </div>
        </div>
    );
};

export default SftpPasswordForm;
