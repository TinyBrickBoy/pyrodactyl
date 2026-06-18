import http from '@/api/http';

export interface SftpPasswordStatus {
    active: boolean;
    expires_at: string | null;
}

export interface GeneratedSftpPassword {
    password: string;
    expires_at: string;
}

const getSftpPasswordStatus = async (): Promise<SftpPasswordStatus> => {
    const { data } = await http.get('/api/client/account/sftp-password');

    return data.data;
};

const generateSftpPassword = async (): Promise<GeneratedSftpPassword> => {
    const { data } = await http.post('/api/client/account/sftp-password');

    return data.data;
};

const revokeSftpPassword = async (): Promise<void> => {
    await http.delete('/api/client/account/sftp-password');
};

export { getSftpPasswordStatus, generateSftpPassword, revokeSftpPassword };
