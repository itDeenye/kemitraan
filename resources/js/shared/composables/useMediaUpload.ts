import { ref } from 'vue';
import api from '@/shared/services/api';

export function useMediaUpload() {
    const isUploading = ref(false);
    const progress = ref(0);
    const error = ref<string | null>(null);
    const mediaId = ref<string | null>(null);

    /** Determine panel prefix based on current URL */
    const getPanel = () => {
        return typeof window !== 'undefined' && window.location.pathname.startsWith('/admin')
            ? 'admin'
            : 'member';
    };

    const upload = async (file: File, collection: string = 'default') => {
        isUploading.value = true;
        progress.value = 0;
        error.value = null;
        mediaId.value = null;
        const panel = getPanel();

        try {
            // 1. Initialize upload session
            const initPayload = {
                collection,
                filename: file.name,
                mime_type: file.type,
                size: file.size,
                checksum: null
            };
            const initRes = await api.post(`/${panel}/media/uploads`, initPayload);
            const data = initRes.data.data;
            
            mediaId.value = data.id;
            const chunkSize = data.chunk_size;
            const totalChunks = data.total_chunks;

            // 2. Upload chunks sequentially
            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('chunk', chunk);
                
                // POST chunk (1-indexed)
                await api.post(`/${panel}/media/uploads/${mediaId.value}/chunks/${i + 1}`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });

                // Update progress
                progress.value = Math.round(((i + 1) / totalChunks) * 100);
            }

            // 3. Complete upload
            const completeRes = await api.post(`/${panel}/media/uploads/${mediaId.value}/complete`);
            
            // If the complete response includes a URL, use it. Otherwise, use the content endpoint.
            const url = completeRes.data?.data?.url || 
                        `${api.defaults.baseURL || ''}/${panel}/media/uploads/${mediaId.value}/content`;
            
            return url;
            
        } catch (e: any) {
            error.value = e.response?.data?.message || e.message;
            
            // Cleanup on failure
            if (mediaId.value) {
                cancel().catch(() => {});
            }
            throw e;
        } finally {
            isUploading.value = false;
        }
    };

    const cancel = async () => {
        if (mediaId.value) {
            const panel = getPanel();
            await api.delete(`/${panel}/media/uploads/${mediaId.value}`);
            isUploading.value = false;
            progress.value = 0;
            mediaId.value = null;
        }
    };

    return {
        isUploading,
        progress,
        error,
        upload,
        cancel
    };
}

