export interface PaginatedResponse<T> {
    data: T[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
}

export interface ApiResponse<T = unknown> {
    success: boolean
    message: string
    data: T
}

export interface AuthUser {
    id?: number
    account_id?: number
    username: string
    name?: string
    email?: string
    mobile_phone?: string
    image?: string | null
    last_login_at: string
    has_bank_account?: boolean
    is_default_password?: boolean
    role: {
        id: number
        name: string
        type?: string
    }
    member?: {
        id: number
        code: string
        name: string
        email: string
        mobile_phone: string
        level: {
            id?: number
            code?: string
            name?: string
            min_order?: number
        }
        image: string | null
        is_stockist: boolean
    }
}

export interface NavItem {
    title: string
    icon?: string
    to?: string
    href?: string
    children?: NavItem[]
    badge?: string | number
    divider?: boolean
}

export interface UnilevelNode {
    id: string
    name: string
    sponsorId?: string
    joinDate?: string
    children?: UnilevelNode[]
}
