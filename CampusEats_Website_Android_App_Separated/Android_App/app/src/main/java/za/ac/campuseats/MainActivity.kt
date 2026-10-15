package za.ac.campuseats

import android.annotation.SuppressLint
import android.app.Activity
import android.app.AlertDialog
import android.content.Intent
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.util.Base64
import android.view.View
import android.webkit.CookieManager
import android.webkit.JsResult
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import java.net.URLEncoder
import java.security.MessageDigest
import java.security.SecureRandom

/** Kotlin Android host for the shared campus interface.
 * The WebView makes same-origin API calls to PHP. It never connects to MySQL.
 * Only the configured server may render inside the application; Google OAuth
 * runs in the system browser and returns a proof-bound, single-use ticket.
 */
class MainActivity : Activity()
{
    private lateinit var webView: WebView
    private lateinit var progress: ProgressBar
    private lateinit var errorPanel: LinearLayout
    private val preferences by lazy { getSharedPreferences("sso_flow", MODE_PRIVATE) }
    private val siteUrl by lazy { BuildConfig.BASE_URL.trimEnd('/') + "/" }
    private val ssoUrl by lazy { BuildConfig.SSO_URL.trimEnd('/') + "/" }
    private val baseUri by lazy { Uri.parse(siteUrl) }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?)
    {
        super.onCreate(savedInstanceState)
        val root = LinearLayout(this)
        root.orientation = LinearLayout.VERTICAL
        root.setBackgroundColor(Color.rgb(247, 248, 244))
        // Apply system-bar insets on Android 15 without obscuring navigation.
        root.setOnApplyWindowInsetsListener { view, insets ->
            view.setPadding(insets.systemWindowInsetLeft, insets.systemWindowInsetTop,
                insets.systemWindowInsetRight, insets.systemWindowInsetBottom)
            insets
        }
        progress = ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal)
        progress.max = 100
        root.addView(progress, LinearLayout.LayoutParams(-1, 6))
        webView = WebView(this)
        root.addView(webView, LinearLayout.LayoutParams(-1, 0, 1f))
        errorPanel = LinearLayout(this)
        errorPanel.orientation = LinearLayout.VERTICAL
        errorPanel.setPadding(40, 60, 40, 40)
        val message = TextView(this)
        message.text = "CampusEats cannot connect. Check your connection and make sure WampServer is running."
        message.textSize = 18f
        errorPanel.addView(message)
        val retry = Button(this)
        retry.text = "Try again"
        retry.setOnClickListener(
        {
            errorPanel.visibility = View.GONE
            webView.visibility = View.VISIBLE
            webView.loadUrl(siteUrl + "index.php")
        })
        errorPanel.addView(retry)
        errorPanel.visibility = View.GONE
        root.addView(errorPanel, LinearLayout.LayoutParams(-1, -1))
        setContentView(root)
        root.requestApplyInsets()

        with(webView.settings)
        {
            javaScriptEnabled = true
            domStorageEnabled = true
            allowFileAccess = false
            allowContentAccess = false
            mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            setSupportMultipleWindows(false)
            userAgentString += " CampusEatsAndroid/1.0"
        }
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, false)
        WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG)
        webView.webChromeClient = object : WebChromeClient()
        {
            override fun onProgressChanged(view: WebView, newProgress: Int)
            {
                progress.progress = newProgress
                progress.visibility = if (newProgress < 100) View.VISIBLE else View.GONE
            }

            override fun onJsConfirm(view: WebView, url: String, message: String, result: JsResult): Boolean
            {
                AlertDialog.Builder(this@MainActivity)
                    .setTitle("CampusEats")
                    .setMessage(message)
                    .setPositiveButton("Confirm") { _, _ -> result.confirm() }
                    .setNegativeButton("Go back") { _, _ -> result.cancel() }
                    .setOnCancelListener { result.cancel() }
                    .show()
                return true
            }
        }
        webView.webViewClient = object : WebViewClient()
        {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean
            {
                val uri = request.url
                if (isTrusted(uri))
                {
                    if (uri.path?.endsWith("/sso.php") == true && uri.getQueryParameter("mode") != "redeem")
                    {
                        beginSingleSignOn()
                        return true
                    }
                    return false
                }
                // Do not allow arbitrary redirects, file URLs or JavaScript URLs
                // to enter the authenticated application surface.
                return true
            }

            override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError)
            {
                if (request.isForMainFrame)
                {
                    webView.visibility = View.GONE
                    errorPanel.visibility = View.VISIBLE
                }
            }
        }
        if (!BuildConfig.DEBUG && baseUri.scheme != "https")
        {
            message.text = "Release builds require an HTTPS server URL. Update CAMPUS_BASE_URL before building."
            webView.visibility = View.GONE
            errorPanel.visibility = View.VISIBLE
            return
        }
        if (!handleAuthIntent(intent))
        {
            if (savedInstanceState == null || webView.restoreState(savedInstanceState) == null)
            {
                webView.loadUrl(siteUrl + "index.php")
            }
        }
    }

    private fun isTrusted(uri: Uri): Boolean
    {
        return uri.scheme == baseUri.scheme && uri.host == baseUri.host &&
            uri.port == baseUri.port && uri.path.orEmpty().startsWith(baseUri.path.orEmpty())
    }

    private fun beginSingleSignOn()
    {
        val bytes = ByteArray(32)
        SecureRandom().nextBytes(bytes)
        val verifier = Base64.encodeToString(bytes, Base64.URL_SAFE or Base64.NO_WRAP or Base64.NO_PADDING)
        val digest = MessageDigest.getInstance("SHA-256").digest(verifier.toByteArray(Charsets.US_ASCII))
        val challenge = Base64.encodeToString(digest, Base64.URL_SAFE or Base64.NO_WRAP or Base64.NO_PADDING)
        preferences.edit().putString("verifier", verifier).putLong("started", System.currentTimeMillis()).apply()
        val uri = Uri.parse(ssoUrl + "sso.php").buildUpon()
            .appendQueryParameter("challenge", challenge).build()
        try
        {
            startActivity(Intent(Intent.ACTION_VIEW, uri).addCategory(Intent.CATEGORY_BROWSABLE))
        }
        catch (_: android.content.ActivityNotFoundException)
        {
            preferences.edit().clear().apply()
            AlertDialog.Builder(this).setMessage("Install a browser to use Google sign-in.")
                .setPositiveButton("OK", null).show()
        }
    }

    /** A stolen deep link is insufficient: redemption also requires the verifier.
     * The server consumes the ticket once and establishes a new cookie session.
     */
    private fun handleAuthIntent(intent: Intent?): Boolean
    {
        val uri = intent?.data ?: return false
        if (uri.scheme != "campuseats" || uri.host != "auth") return false
        val ticket = uri.getQueryParameter("ticket") ?: return false
        val verifier = preferences.getString("verifier", null) ?: return false
        val started = preferences.getLong("started", 0)
        if (!ticket.matches(Regex("[a-f0-9]{64}")) || System.currentTimeMillis() - started > 600000)
        {
            preferences.edit().clear().apply()
            return false
        }
        preferences.edit().clear().apply()
        val body = "ticket=" + URLEncoder.encode(ticket, "UTF-8") +
            "&verifier=" + URLEncoder.encode(verifier, "UTF-8")
        errorPanel.visibility = View.GONE
        webView.visibility = View.VISIBLE
        webView.postUrl(siteUrl + "sso.php?mode=redeem", body.toByteArray(Charsets.UTF_8))
        return true
    }

    override fun onNewIntent(intent: Intent)
    {
        super.onNewIntent(intent)
        setIntent(intent)
        handleAuthIntent(intent)
    }

    override fun onSaveInstanceState(outState: Bundle)
    {
        webView.saveState(outState)
        super.onSaveInstanceState(outState)
    }

    override fun onPause()
    {
        CookieManager.getInstance().flush()
        super.onPause()
    }

    @Deprecated("Activity back navigation is retained for the minimum API level")
    override fun onBackPressed()
    {
        if (webView.canGoBack())
        {
            webView.goBack()
        }
        else
        {
            super.onBackPressed()
        }
    }

    override fun onDestroy()
    {
        webView.destroy()
        super.onDestroy()
    }
}
