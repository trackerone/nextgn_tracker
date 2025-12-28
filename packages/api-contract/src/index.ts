export interface TorrentDto {
  id: number;
  name: string;
  status: 'approved' | 'rejected' | 'soft_deleted';
}
