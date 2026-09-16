/** @jsxImportSource @emotion/react */
import * as React from 'react'
import { emit, on } from '@/scripts/event'
import { t } from '@/scripts/i18n'
import * as cssUtils from '@/styles/utils'

const eventId = Symbol()

interface TurnstileRenderOptions {
  sitekey: string
  callback: (token: string) => void
  'expired-callback'?: () => void
  'error-callback'?: () => void
  'timeout-callback'?: () => void
  theme?: 'light' | 'dark' | 'auto'
  size?: 'normal' | 'flexible' | 'compact'
}

interface TurnstileApi {
  render: (el: HTMLElement, options: TurnstileRenderOptions) => string
  reset: (widgetId?: string) => void
  execute: (widgetId?: string) => void
  remove: (widgetId: string) => void
}

declare global {
  interface Window {
    turnstile?: TurnstileApi
  }
}

// Cloudflare Turnstile 脚本（显式渲染），全站单例加载
const SCRIPT_ID = 'cf-turnstile-script'
let scriptPromise: Promise<TurnstileApi> | null = null

function loadTurnstile(): Promise<TurnstileApi> {
  if (window.turnstile) {
    return Promise.resolve(window.turnstile)
  }
  if (!scriptPromise) {
    scriptPromise = new Promise((resolve, reject) => {
      const existing = document.getElementById(
        SCRIPT_ID,
      ) as HTMLScriptElement | null
      const script = existing ?? document.createElement('script')
      script.id = SCRIPT_ID
      script.src =
        'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
      script.async = true
      script.defer = true
      script.onload = () => {
        if (window.turnstile) {
          resolve(window.turnstile)
        } else {
          reject(new Error('Turnstile failed to initialize'))
        }
      }
      script.onerror = () =>
        reject(new Error('Failed to load Turnstile script'))
      if (!existing) {
        document.head.appendChild(script)
      }
    })
  }
  return scriptPromise
}

type State = {
  value: string
  time: number
  sitekey: string
}

class Captcha extends React.Component<Record<string, unknown>, State> {
  state: State
  containerRef: React.MutableRefObject<HTMLDivElement | null>
  widgetId: string | null

  constructor(props: Record<string, unknown>) {
    super(props)
    this.state = {
      value: '',
      time: Date.now(),
      sitekey: blessing.extra.turnstile,
    }
    this.containerRef = React.createRef()
    this.widgetId = null
  }

  componentDidMount() {
    if (this.state.sitekey) {
      loadTurnstile()
        .then((turnstile) => {
          if (this.containerRef.current && this.widgetId === null) {
            this.widgetId = turnstile.render(this.containerRef.current, {
              sitekey: this.state.sitekey,
              theme: 'auto',
              callback: this.handleVerify,
              'expired-callback': this.handleExpire,
              'timeout-callback': this.handleExpire,
              'error-callback': this.handleExpire,
            })
          }
        })
        .catch(() => {
          // 脚本加载失败时保持空 token，提交会被后端拒绝并提示
        })
    }
  }

  componentWillUnmount() {
    if (this.widgetId !== null) {
      window.turnstile?.remove(this.widgetId)
      this.widgetId = null
    }
  }

  execute = async () => {
    // 未配置 Turnstile 时回退到图片验证码输入值
    if (!this.state.sitekey) {
      return this.state.value
    }
    // managed 模式下 token 通常已自动生成
    if (this.state.value) {
      return this.state.value
    }
    return new Promise<string>((resolve) => {
      const off = on(eventId, (value: string) => {
        resolve(value)
        off()
      })
      window.turnstile?.execute(this.widgetId ?? undefined)
    })
  }

  reset = () => {
    if (this.widgetId !== null && window.turnstile) {
      window.turnstile.reset(this.widgetId)
      this.setState({ value: '' })
    } else {
      this.setState({ time: Date.now() })
    }
  }

  handleValueChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    this.setState({ value: event.target.value })
  }

  handleVerify = (token: string) => {
    emit(eventId, token)
    this.setState({ value: token })
  }

  handleExpire = () => {
    this.setState({ value: '' })
    if (this.widgetId !== null) {
      window.turnstile?.reset(this.widgetId)
    }
  }

  handleRefresh = () => {
    this.setState({ time: Date.now() })
  }

  render() {
    return this.state.sitekey ? (
      <div className="mb-2">
        <div ref={this.containerRef} />
      </div>
    ) : (
      <div className="d-flex">
        <div className="form-group mb-3 mr-2">
          <input
            type="text"
            className="form-control"
            placeholder={t('auth.captcha')}
            required
            value={this.state.value}
            onChange={this.handleValueChange}
          />
        </div>
        <img
          src={`${blessing.base_url}/auth/captcha?v=${this.state.time}`}
          alt={t('auth.captcha')}
          css={cssUtils.pointerCursor}
          height={34}
          title={t('auth.change-captcha')}
          onClick={this.handleRefresh}
        />
      </div>
    )
  }
}

export default Captcha
